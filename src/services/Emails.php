<?php

namespace webdna\craftemailcontenteditor\services;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\App;

use craft\helpers\Json;
use craft\mail\Message;
use craft\models\SystemMessage;
use craft\web\View;
use webdna\craftemailcontenteditor\EmailContentEditor;
use webdna\craftemailcontenteditor\fields\EmailSettings as FieldsEmailSettings;
use webdna\craftemailcontenteditor\models\EmailSettings as EmailSettingsModel;
use webdna\craftemailcontenteditor\models\Recipient;
use webdna\craftemailcontenteditor\twig\Sandbox;
use yii\base\Component;

/**
 * Emails service
 */
class Emails extends Component
{
    /** Used as the test recipient when there's no test user or logged-in user, e.g. from the console. */
    private const PLACEHOLDER_RECIPIENT = [
        'email' => 'test@example.com',
        'firstName' => 'Test',
        'lastName' => 'Recipient',
        'friendlyName' => 'Test',
    ];

    private ?Sandbox $_sandbox = null;

    public function findEntryForEmail(string $messageKey, int $siteId): ?Entry
    {
        $emailSettingsFields = Craft::$app->getFields()->getFieldsByType(FieldsEmailSettings::class);

        $entries = Entry::find();

        if ($siteId) {
            $entries->siteId($siteId);
        }

        $fields = collect($emailSettingsFields)->map(function($f) {
            return $f->handle;
        });
        foreach ($fields as $field) {
            $entries->$field(":notempty:");
        }

        $entries->all();

        foreach ($entries as $entry) {
            $emailSettingsHandle = $this->getEmailSettingsFieldHandle($entry);
            $emailSettingsField = $entry->getFieldValue($emailSettingsHandle);
            if (
                !empty($emailSettingsField)
                && is_array($emailSettingsField)
                && array_key_exists('messageKey', $emailSettingsField)
                && $emailSettingsField['messageKey'] == $messageKey) {
                return $entry;
            }
        }

        return null;
    }

    public function getAllEmailSettingsFieldsColumnNames(): array
    {
        $emailSettingsFields = Craft::$app->getFields()->getFieldsByType(FieldsEmailSettings::class);
        $fields = [];
        foreach ($emailSettingsFields as $field) {
            $columnName = '';
            /*if ($field->columnPrefix) {
                $columnName .= $field->columnPrefix . '_';
            }*/
            $columnName .= 'field_' . $field->handle;
            if ($field->columnSuffix) {
                $columnName .= '_' . $field->columnSuffix;
            }
            $fields[$field->handle] = [
                'handle' => $field->handle,
                'columnName' => $columnName,
            ];
        }

        return $fields;
    }

    public function getEmailSettingsFieldHandle(Entry $entry): ?string
	{
	    foreach ($entry->getFieldLayout()?->getCustomFields() ?? [] as $field) {
	        if ($field instanceof FieldsEmailSettings) {
	            return $field->handle;
	        }
	    }

	    return null;
	}

    public function getAllCommerceEmails(): array
    {
        $emails = [];
        if (Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
            $commerceEmails = \craft\commerce\Plugin::getInstance()->getEmails()->getAllEmails();
            if ($commerceEmails) {
                foreach ($commerceEmails as $commerceEmail) {
                    $emails['commerceEmail' . $commerceEmail->id] = $commerceEmail->name . " (Commerce)";
                }
            }
        }
        return $emails;
    }

    public function getSystemMessageByKey($key): ?SystemMessage
    {
        foreach (Craft::$app->getSystemMessages()->getAllMessages() as $message) {
            if ($message['key'] == $key) {
                return $message;
            }
        }
        return null;
    }

    /**
     * Adds an email entry's test recipient, test order and test variables to a template context.
     *
     * @param bool $checkPermissions Whether to only use a test user or test order that the logged-in user is
     * allowed to view. When they can't view the test user, they're used as the test recipient themselves; when
     * they can't view the test order, it's left out. Pass `false` only where there's no logged-in user to check
     * against, such as console commands.
     */
    public function mergeTestVariables(EmailSettingsModel $emailSettings, array $context, bool $checkPermissions = true): array
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $testVariables = $emailSettings->testVariables;

        $testUser = $emailSettings->getTestUser();
        if ($testUser && $checkPermissions && !$this->_canView($testUser, $currentUser)) {
            $testUser = $currentUser;
        }
        $context['recipient'] = new Recipient($testUser ?? self::PLACEHOLDER_RECIPIENT);

        if (
            Craft::$app->getPlugins()->isPluginEnabled('commerce')
            && !empty($emailSettings->testOrderId)
            ) {
            $order = $emailSettings->getTestOrder();
            if ($order && (!$checkPermissions || $this->_canView($order, $currentUser))) {
                $context['order'] = $order;
                $context['orderHistory'] = $emailSettings->getTestOrderHistory();
                if ($customer = $order->getCustomer()) {
                    $context['recipient'] = new Recipient($customer);
                }
            }
        }

        if ($testVariables) {
            $testJson = Json::decodeIfJson($testVariables);
            if ($testJson && is_array($testJson)) {
                foreach ($testJson as $key => $value) {
                    $context[$key] = $value;
                }
            }
        }

        return $context;
    }

    /**
     * Renders Twig written by control panel users (email subjects and content) in the sandbox.
     *
     * @throws \Twig\Error\Error if the Twig is invalid or uses something the sandbox doesn't allow
     */
    public function sandboxRender(string $output, array $variables): string
    {
        return $this->getSandbox()->render($output, $variables);
    }

    public function getSandbox(): Sandbox
    {
        return $this->_sandbox ??= new Sandbox(
            EmailContentEditor::getInstance()->getSettings()->sandbox,
            Craft::$app->charset,
        );
    }

    /**
     * Sends an email entry to the logged-in user, using its test data.
     *
     * Test emails always go to the person sending them (or, from the console, to the test user), so they can't
     * be used to send content to other people.
     */
    public function sendTestEmail(int $id, int $siteId): bool
    {
        $settings = App::mailSettings();
        $siteService = Craft::$app->getSites();
        $originalSite = $siteService->getCurrentSite();
        $entry = Entry::find()->id($id)->siteId($siteId)->one();
        if (!$entry) {
            return false;
        }
        $fieldHandle = $this->getEmailSettingsFieldHandle($entry);
        $emailSettings = new EmailSettingsModel($entry->getFieldValue($fieldHandle));
        $to = Craft::$app->getUser()->getIdentity()?->email ?? $emailSettings->getTestUser()?->email;
        if (!$to) {
            return false;
        }

        $siteService->setCurrentSite($entry->siteId);
        try {
            $variables['entry'] = $entry;
            $variables = $this->mergeTestVariables($emailSettings, $variables);

            $message = new Message();
            $message->setFrom([App::parseEnv($settings['fromEmail']) => App::parseEnv($settings['fromName'])]);
            $message->setTo($to);

            $message = $this->buildEmail($entry,$message,$variables);
        } finally {
            $siteService->setCurrentSite($originalSite);
        }

        if ($message == false) {
            return false;
        } else {
            return Craft::$app->mailer->send($message);
        }
    }

    public function buildEmail(Entry $entry, Message $message, array $variables): mixed
    {
        $fieldHandle = $this->getEmailSettingsFieldHandle($entry);
        $emailSettings = new EmailSettingsModel($entry->getFieldValue($fieldHandle));
        $this->_createSubjectLine($emailSettings->subject,$variables,$message);
        $this->_createBody($entry,$variables,$message);
        return $message;
    }

    /**
     * Renders an email entry's site template, before the Twig in its content is rendered in the sandbox.
     *
     * @return string|null The HTML, or `null` if the entry's section has no template for its site
     */
    public function renderEntryTemplate(Entry $entry, array $variables): ?string
    {
        $template = null;
        $siteSettings = Craft::$app->getEntries()->getSectionSiteSettings($entry->sectionId);
        foreach ($siteSettings as $setting) {
            if ($setting['siteId'] == $entry->siteId) {
                $template = $setting['template'];
            }
        }
        if (!$template) {
            return null;
        }

        return Craft::$app->getView()->renderTemplate($template, $variables, View::TEMPLATE_MODE_SITE);
    }


    // Private Methods
    // =========================================================================

    private function _canView(\craft\base\ElementInterface $element, ?User $user): bool
    {
        return $user !== null && Craft::$app->getElements()->canView($element, $user);
    }

    private function _createSubjectLine(string $subject, array $variables, Message $message): void
    {
        try {
            $message->setSubject($this->sandboxRender($subject, $variables));
        } catch (\Throwable $e) {
            // Leave the subject the message already has (the system message's or Commerce email's own subject),
            // so the email still goes out
            if (!$message->getSubject()) {
                $message->setSubject($subject);
            }
            $error = Craft::t('email-content-editor', 'Email template parse error for email "{email}" in the subject. Template error: "{message}"', [
                'email' => $message->key,
                'message' => $e->getMessage(),
            ]);
            Craft::error($error, __METHOD__);
        }
    }

    private function _createBody(Entry $entry, array $variables, Message $message): void
    {
        try {
            $htmlBody = $this->renderEntryTemplate($entry, $variables);
            if ($htmlBody === null) {
                return;
            }
            // Lets double render incase the user has any {variable} stuff in there.
            try {
                $htmlBody = $this->sandboxRender($htmlBody,$variables);
            } catch (\Throwable $e) {
                $error = Craft::t('email-content-editor', 'Email template parse error for email {email}. Failed to render content variables. Template error: {message}', [
                    'email' => $message->key,
                    'message' => $e->getMessage(),
                ]);
                Craft::error($error, __METHOD__);
            }
            $message->setHtmlBody($htmlBody);
        } catch (\Throwable $e) {
            $error = Craft::t('email-content-editor', 'Email template parse error for email {email}. Failed to set bodyHtml. Template error: {message}', [
                'email' => $message->key,
                'message' => $e->getMessage(),
            ]);
            Craft::error($error, __METHOD__);
        }
    }
}
