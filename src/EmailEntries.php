<?php

namespace mikeymeister\craftemailentries;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\ElementEvent;
use craft\events\RegisterEmailMessagesEvent;

use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;
use craft\helpers\ElementHelper;
use craft\helpers\Json;
use craft\mail\Mailer;

use craft\services\Elements;
use craft\services\SystemMessages;

use craft\services\UserPermissions;
use craft\web\View;

use craft\commerce\services\Emails as CommerceEmails;
use craft\commerce\events\MailEvent;


use mikeymeister\craftemailentries\models\Email;
use mikeymeister\craftemailentries\models\Settings;
use mikeymeister\craftemailentries\services\Emails;

use yii\base\Event;

/**
 * Email Entries plugin
 *
 * @method static EmailEntries getInstance()
 * @author mikeymeister
 * @copyright mikeymeister
 * @license MIT
 * @property-read Emails $emails
 */
class EmailEntries extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;


    public static function config(): array
    {
        return [
            'components' => ['emails' => Emails::class],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->_registerPermissions();
            $this->_registerHooks();
            $this->_attachEventHandlers();
            $this->_attachCommerceEventHandlers();
            // ...
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        $allSections = [];
        foreach (Craft::$app->getSections()->getAllSections() as $section) {
            $allSections[] = [
                'label' => $section->name,
                'value' => $section->id
            ];
        }
        $sectionIdsInput = 'sectionIds';
        $view = Craft::$app->getView();
        $view->registerJsWithVars(fn($sectionIdsInput) => <<<JS
            $('#' + $sectionIdsInput).selectize({
                plugins: ['remove_button'],
                });
            JS, [
            $view->namespaceInputId($sectionIdsInput),
        ]);

        return \Craft::$app->getView()->renderTemplate(
            'email-entries/settings',
            [ 
                'settings' => $this->getSettings(),
                'allSections' => $allSections
            ]
        );
    }

    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => "Email Entries",
                    'permissions' => [
                        'setTestVariables' => [
                            'label' => 'Set Test Variables',
                        ],
                        'manageSettings' => [
                            'label' => 'Manage Plugin Settings'
                        ],
                        'editEmails' => [
                            'label' => 'Edit Entries\' Email Settings'
                        ],
                        'manageTestVars' => [
                            'label' => 'Manage Test Code for Emails'
                        ],
                        'testEmails' => [
                            'label' => 'Send Test Emails'
                        ]
                    ]
                ];
                // $event->permissions['General']['accessCp']['nested']['accessPlugin-' . $this->id] = [
                //     'label' => Craft::t('app', 'Access {plugin}', ['plugin' => $this->name])
                // ];
            }
        );
    }

    private function _registerHooks():void
    {
        // if the email is in a section set as an email section
        // and they have permission to edit the email or its test variables
        // or to send a test email then show the email settings tab
        // Craft::$app->view->hook('cp.elements.element', function(array &$context) {
        //     // if ($context['elementType'] === Entry::class
        //     //     && $context['elements'])
        //     Craft::dd($context);
        //     $view = Craft::$app->getView();
        //     //Craft::dd($context);
        //     $context['tabs']['email-entries'] = [
        //         'label' => 'Email Settings',
        //         'url' => '#emailEntriesTab',
        //         'class' => null
        //     ];

        //     return $view->renderTemplate('email-entries/email-settings', [
        //         'email' => 'email',
        //     ]);
        // });

    }


    private function _attachEventHandlers(): void
    {



        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineHtmlEvent $event) {
                // ...Add the dropdown here and maybe some test variables
                $entry = $event->sender;
                if ($event->static !== true 
                    && in_array($entry->sectionId,EmailEntries::getInstance()->getSettings()->sectionIds)
                ) {
                    $email = EmailEntries::getInstance()->emails->getEmailByEntry($entry) ?? new Email();
                    $messages = collect(Craft::$app->getSystemMessages()->getAllMessages())->pluck('heading', 'key')->all();
                    $event->html .= Craft::$app->getView()->renderTemplate('email-entries/sidebar', [
                        'email'=>$email,
                        'systemMessages' => $messages
                    ], View::TEMPLATE_MODE_CP);
                }
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_ADDITIONAL_BUTTONS,
            function (DefineHtmlEvent $event) {
                // ...add the send test email
                $entry = $event->sender;
                if ($event->static !== true 
                    && in_array($entry->sectionId,EmailEntries::getInstance()->getSettings()->sectionIds)
                ) {
                    $event->html .= Craft::$app->getView()->renderTemplate('email-entries/button', [
                        'entry'=>$entry,
                    ], View::TEMPLATE_MODE_CP);
                }

            }
        );
        // Register event handlers here ...
        // (see https://craftcms.com/docs/4.x/extend/events.html to get started)
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT, 
            function(ElementEvent $e) {
                if (ElementHelper::isDraftOrRevision($e->element)) {
                    return;
                }
                if (
                    $e->element instanceof Entry 
                    && in_array($e->element->sectionId, $this->getSettings()->sectionIds)
                    && Craft::$app->getRequest()->getIsCpRequest()
                )
                {
                    EmailEntries::getInstance()->emails->saveEmailOnEntrySave($e->element);
                    return;
                }
            }
        );

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $e) {
                if ($e->templateMode == View::TEMPLATE_MODE_SITE) {
                    if (
                        array_key_exists('entry',$e->variables) 
                        && in_array($e->variables['entry']->sectionId, $this->getSettings()->sectionIds) 
                        && Craft::$app->user->checkPermission('testEmails')
                    ) {
                        $email = EmailEntries::getInstance()->emails->getEmailByEntry($e->variables['entry']);
                        
                        if ($email && $email->testVariables) {
                            // Maybe there is twig in there
                            $rendered = Craft::$app->getView()->renderString($email->testVariables, [], View::TEMPLATE_MODE_SITE);
                            $e->variables['testVariables'] = Json::decodeIfJson($rendered);
                            $e->variables['variables']['testVariables'] = $e->variables['testVariables'];
                        }
                    }
                }
            }
        );

        Event::on(
            Mailer::class,
            Mailer::EVENT_BEFORE_SEND,
            function(Event $event) {
                if ($event->message->key != null) {
                    $email = $this->emails->getEmailByKey($event->message->key);
                    if ($email) {  
                        $toEmailArr = array_keys($event->message->getTo());
                        $toEmail = array_pop($toEmailArr);
                        $user = Craft::$app->users->getUserByUsernameOrEmail($toEmail);
                        if (!$user) {
                            $user = [
                                'email' => $toEmail,
                                'firstName' => explode('@',$toEmail)[0],
                                'friendlyName' => explode('@',$toEmail)[0]
                            ];
                        }
                        $entry = Entry::find()
                            ->id($email->elementId)
                            ->siteId(Craft::$app->getSites()->getCurrentSite()->id)
                            ->one();
                        if($entry) {
                            $variables = $event->message->variables;
                            $variables['variables'] = $event->message->variables;
                            $variables['recipient'] = $user;
                            $variables['entry'] = $entry;
                            $event->message = EmailEntries::getInstance()->emails->buildEmail($entry,$event->message,$email,$variables);
                        }
                    }
                }
            }
        ); 
        
        Event::on(
            SystemMessages::class,
            SystemMessages::EVENT_REGISTER_MESSAGES,
            function(RegisterEmailMessagesEvent $event) {
                if ($customEmails = $this->getSettings()->customSystemMessages) {
                    foreach ($customEmails as $customEmail) {
                        $event->messages[] = $customEmail;
                    }
                }
            }
        );
    }

    private function _attachCommerceEventHandlers()
    {
        if (class_exists('craft\\commerce\\services\\Emails')) {
            Event::on(
                CommerceEmails::class, 
                CommerceEmails::EVENT_BEFORE_SEND_MAIL,
                function(MailEvent $e) {
                    //Get the Email Editor Model Associated with the Commerce Email Event
                    $email = EmailEntries::getInstance()->getEmails()->getEmailByKey('commerceEmail'.$e->commerceEmail->id);
    
                    if ($email) {  
                        $toEmailArr = array_keys($e->craftEmail->getTo());
                        $toEmail = array_pop($toEmailArr);
                        $user = Craft::$app->users->getUserByUsernameOrEmail($toEmail);
                        if (!$user) {
                            $user = [
                                'email' => $toEmail,
                                'firstName' => $e->order->billingAddress->firstName ?? explode('@',$toEmail)[0],
                                'friendlyName' => $e->order->billingAddress->firstName ?? explode('@',$toEmail)[0]
                            ];
                        }
                        $entry = Entry::find()
                            ->id($email->id)
                            ->siteId($email->siteId)
                            ->one();
                        if($entry) {
                            $variables['recipient'] = $user;
                            $variables['entry'] = $entry;
                            $variables['order'] = $e->order;
                            $variables['orderHistory'] = $e->orderHistory;
                            $e->craftEmail = EmailEntries::getInstance()->getEmails()->buildEmail($entry,$e->craftEmail,$email,$variables);
                        }
                    }
                }
            );
        }
    }
}
