<?php

namespace webdna\craftemailcontenteditor\services;

use webdna\craftemailcontenteditor\fields\EmailSettings as FieldsEmailSettings;
use webdna\craftemailcontenteditor\models\EmailSettings as EmailSettingsModel;
use webdna\craftemailcontenteditor\models\Recipient;

use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\mail\Message;
use craft\models\SystemMessage;
use craft\web\twig\TemplateLoader;
use Twig\Environment;
use yii\base\Component;

/**
 * Emails service
 */
class Emails extends Component
{
    public function findEntryForEmail($messageKey): ?Entry
    {
        $fields = $this->getAllEmailSettingsFieldsColumnNames();
        $entries = Entry::find();
        foreach ($fields as $key => $value) {
            $entries->andWhere(Db::parseParam('content.'.$value['columnName'], ':notempty:'));
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
            if ($field->columnPrefix) {
                $columnName .= $field->columnPrefix . '_';
            }
            $columnName .= 'field_' . $field->handle;
            if ($field->columnSuffix) {
                $columnName .= '_' . $field->columnSuffix;
            }
            $fields[$field->handle] = [
                'handle' => $field->handle,
                'columnName' => $columnName
            ];
        }

        return $fields;
    }

    public function getEmailSettingsFieldHandle(Entry $entry): ?string
    {
        $emailSettingsFields = Craft::$app->getFields()->getFieldsByType(FieldsEmailSettings::class);
        $emailSettingsFieldsHandles = array_column($emailSettingsFields, 'handle');
        
        foreach ($emailSettingsFieldsHandles as $handle) {
            if (array_key_exists($handle, $entry->getFieldValues())) {
                return $handle;
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
                    $emails['commerceEmail'.$commerceEmail->id] = $commerceEmail->name . " (Commerce)";
                }
            }
        } 
        return $emails;
    }

    public function getSystemMessageByKey($key): ?SystemMessage
    {
        foreach(Craft::$app->getSystemMessages()->getAllMessages() as $message) {
            if ($message['key'] == $key) {
                return $message;
            }
        }
        return null;
    }

    public function mergeTestVariables(EmailSettingsModel $emailSettings, array $context): array 
    {
        $testVariables = $emailSettings->testVariables;

        $context['recipient'] = new Recipient($emailSettings->getTestUser());

        if (
            Craft::$app->getPlugins()->isPluginEnabled('commerce') 
            && !empty($emailSettings->testOrderId)
            ) 
        {
            $order = $emailSettings->getTestOrder();
            $orderHistory = $emailSettings->getTestOrderHistory();
            $context['order'] = $order;
            $context['orderHistory'] = $orderHistory; 
            $context['recipient'] = new Recipient($order->customer);
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

    public function sandboxRender(string $output, array $variables): string {
        // make sure we have no global variables or access to craft.app
        $loader = new TemplateLoader(Craft::$app->getView());
        $twig = new \Twig\Environment($loader, [
            // See: https://github.com/twigphp/Twig/issues/1951
            'cache' => Craft::$app->getPath()->getCompiledTemplatesPath(),
            'auto_reload' => true,
            'charset' => Craft::$app->charset,
        ]);
        
        return $twig->createTemplate($output)->render($variables);
    }
    
    public function sendTestEmail(int $id): bool
    {   
		$settings = App::mailSettings();
        $entry = Entry::find()->id($id)->one();
        $fieldHandle = $this->getEmailSettingsFieldHandle($entry);
        $emailSettings = new EmailSettingsModel($entry->getFieldValue($fieldHandle));
        
        $variables['entry'] = $entry;
        $variables = $this->mergeTestVariables($emailSettings, $variables);

        $message = new Message;
        $message->setFrom([App::parseEnv($settings['fromEmail']) => App::parseEnv($settings['fromName'])]);
        $message->setTo($emailSettings->getTestUser()->email);

        $message = $this->buildEmail($entry,$message,$variables);

        if ($message == false){   
            return false;
        } else {
            return Craft::$app->mailer->send($message);
        }
    }

    public function buildEmail(Entry $entry, Message $message, Array $variables): mixed
    {

        $fieldHandle = $this->getEmailSettingsFieldHandle($entry);
        $emailSettings = new EmailSettingsModel($entry->getFieldValue($fieldHandle));
        $this->_createSubjectLine($emailSettings->subject,$variables,$message);
        $this->_createBody($entry,$variables,$message);
        return $message;
    }


    // Private Methods
    // =========================================================================

    private function _createSubjectLine(string $subject, array $variables, Message $message): void
    {
        $subject = $this->sandboxRender($subject, $variables);

        try {
            $message->setSubject($subject);
        } catch (\Exception $e) {
            $error = Craft::t('email-content-editor', `Email template parse error for system message "{email}" in "Subject:". To: "{to}". Template error: "{message}"`, [
                'email' => $message->key,
                'to' => $message->getTo(),
                'message' => $e->getMessage()
            ]);
            Craft::error($error, __METHOD__);
        }
            
        return;
    }

    private function _createBody(Entry $entry, array $variables, Message $message): void
    {
        $view = Craft::$app->getView();
        $siteSettings = Craft::$app->getSections()->getSectionSiteSettings($entry->sectionId);
        foreach ($siteSettings as $setting) {
            if ($setting['siteId'] == $entry->siteId ) {
                $template = $setting['template'];
            }
        }
        if (!$template) {
            return;
        }
        
        try {
            $htmlBody = $view->renderTemplate($template, $variables, $view::TEMPLATE_MODE_SITE);
            // Lets double render incase the user has any {variable} stuff in there.
            try {
                $htmlBody = $this->sandboxRender($htmlBody,$variables);

            } catch (\Exception $e) {
                $error = Craft::t('email-content-editor', 'Email template parse error for email {email}. Failed to render content variables. Template error: {message}', [
                    'email' => $message->key,
                    'message' => $e->getMessage()
                ]);
                Craft::error($error, __METHOD__);
            }
            $message->setHtmlBody($htmlBody);
        } catch (\Exception $e) {
            $error = Craft::t('email-content-editor', 'Email template parse error for email {email}. Failed to set bodyHtml. Template error: {message}', [
                'email' => $message->key,
                'message' => $e->getMessage()
            ]);
            Craft::error($error, __METHOD__);
        }
        //   Craft::dd($htmlBody); 
        return;
    }
}