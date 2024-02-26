<?php

namespace webdna\craftemailentries;

use webdna\craftemailentries\fields\EmailSettings;
use webdna\craftemailentries\models\EmailSettings as EmailSettingsModel;
use webdna\craftemailentries\models\Settings;
use webdna\craftemailentries\services\Emails;

use craft\commerce\events\MailEvent;
use craft\commerce\services\Emails as CommerceEmails;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;
use craft\mail\Mailer;
use craft\services\Fields;
use craft\services\SystemMessages;
use craft\services\UserPermissions;
use craft\web\View;
use yii\base\Event;

/**
 * Email Entries plugin
 *
 * @method static EmailEntries getInstance()
 * @author webdna
 * @copyright webdna
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
            $this->_attachEventHandlers();
            if (Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
                $this->_attachCommerceEventHandlers();
            }
        });
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = EmailSettings::class;
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        if (!Craft::$app->user->checkPermission('manageEmailEntriesSettings')) {
            return '';
        }
        return \Craft::$app->getView()->renderTemplate(
            'email-entries/settings',
            [ 
                'settings' => $this->getSettings(),
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
                        'manageEmailEntriesSettings' => [
                            'label' => 'Manage Plugin Settings'
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

    private function _attachEventHandlers(): void
    {
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_ADDITIONAL_BUTTONS,
            function (DefineHtmlEvent $event) {
                // ...add the send test email
                $entry = $event->sender;
                if ($event->static !== true 
                    && EmailEntries::getInstance()->emails->getEmailSettingsFieldHandle($entry)
                    && Craft::$app->user->checkPermission('testEmails')
                ) {
                    $event->html .= Craft::$app->getView()->renderTemplate('email-entries/button', [
                        'entry'=>$entry,
                    ], View::TEMPLATE_MODE_CP);
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
                        && $settingsFieldHandle = EmailEntries::getInstance()->emails->getEmailSettingsFieldHandle($e->variables['entry']) 
                    ) {
                        $entry = $e->variables['entry'];
                        $emailSettings = new EmailSettingsModel($entry->getFieldValue($settingsFieldHandle));
                        $variables = EmailEntries::getInstance()->emails->mergeTestVariables($emailSettings,$e->variables);
                        $e->variables = $variables;
                    }
                }
            }
        );

        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            function (TemplateEvent $e) {
                if (
                    array_key_exists('entry',$e->variables) 
                    && EmailEntries::getInstance()->emails->getEmailSettingsFieldHandle($e->variables['entry']) 
                ) {
                    $e->output = EmailEntries::getInstance()->emails->reRenderTemplateForTwig($e->output, $e->variables);
                }
            }
        );

        Event::on(
            Mailer::class,
            Mailer::EVENT_BEFORE_SEND,
            function(Event $event) {
                if ($event->message->key != null) {

                    // is there an entry set up to modify this system message?
                    $entry = EmailEntries::getInstance()->emails->findEntryForEmail($event->message->key);
                    if ($entry) {  
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
                        
                        $variables = $event->message->variables;
                        $variables['variables'] = $event->message->variables;
                        $variables['recipient'] = $user;
                        $variables['entry'] = $entry;
                        
                        $event->message = EmailEntries::getInstance()->emails->buildEmail($entry,$event->message,$variables);
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
            Event::on(
                CommerceEmails::class, 
                CommerceEmails::EVENT_BEFORE_SEND_MAIL,
                function(MailEvent $e) {
                    //Get the Email Entry Associated with the Commerce Email Event
                    $emailEntry = EmailEntries::getInstance()->emails->findEntryForEmail('commerceEmail'.$e->commerceEmail->id);
                    if ($emailEntry) {  
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
                        
                        if($emailEntry) {
                            $variables['recipient'] = $user;
                            $variables['entry'] = $emailEntry;
                            $variables['order'] = $e->order;
                            $variables['orderHistory'] = $e->orderHistory;
                            $e->craftEmail = EmailEntries::getInstance()->emails->buildEmail($emailEntry,$e->craftEmail,$variables);
                        }
                    }
                }
            );
        
    }
}
