<?php

namespace webdna\craftemailentries\services;

use webdna\craftemailentries\fields\EmailSettings as FieldsEmailSettings;
use webdna\craftemailentries\models\EmailSettings as EmailSettingsModel;


use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\mail\Message;
use craft\models\SystemMessage;
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
            if ($entry->getFieldValue($emailSettingsHandle)['messageKey'] == $messageKey) {
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

        $context['recipient'] = $emailSettings->getTestUser();

        if (
            Craft::$app->getPlugins()->isPluginEnabled('commerce') 
            && !empty($emailSettings->testOrderId)
            ) 
        {
            $order = $emailSettings->getTestOrder();
            $context['order'] = $order;
            $context['orderHistory'] = craft\commerce\Plugin::getInstance()->getOrderHistories()->createOrderHistoryFromOrder($order,null);
            $context['recipient'] = $order->customer;
        }

        if ($testVariables) {
            $rendered = Craft::$app->getView()->renderString($testVariables, $context, Craft::$app->getView()::TEMPLATE_MODE_SITE);
            $testVariables = Json::decodeIfJson($rendered);
            if ($testVariables) {
                foreach ($testVariables as $key => $value) {
                    $context[$key] = $value;
                }
            }
        }

        return $context;
    }

    public function reRenderTemplateForTwig(string $output, array $variables): string
    {
        return Craft::$app->getView()->renderString($output, $variables, Craft::$app->getView()::TEMPLATE_MODE_SITE);
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
            Craft::$app->mailer->send($message);
            return true;
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

    private function _createSubjectLine($subject,$variables,$message): void
    {
        $view = Craft::$app->getView();
        $subject = $view->renderString($subject, $variables, $view::TEMPLATE_MODE_SITE);

        try {
            $message->setSubject($subject);
        } catch (\Exception $e) {
            $error = Craft::t('email-entries', `Email template parse error for system message "{email}" in "Subject:". To: "{to}". Template error: "{message}"`, [
                'email' => $message->key,
                'to' => $message->getTo(),
                'message' => $e->getMessage()
            ]);
            Craft::error($error, __METHOD__);
        }
            
        return;
    }

    private function _createBody($entry,$variables,$message): void
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
                $htmlBody = $view->renderString($htmlBody,$variables,$view::TEMPLATE_MODE_SITE);

            } catch (\Exception $e) {
                $error = Craft::t('email-entries', 'Email template parse error for email {email}. Failed to render content variables. Template error: {message}', [
                    'email' => $message->key,
                    'message' => $e->getMessage()
                ]);
                Craft::error($error, __METHOD__);
            }
            $message->setHtmlBody($htmlBody);
        } catch (\Exception $e) {
            $error = Craft::t('email-entries', 'Email template parse error for email {email}. Failed to set bodyHtml. Template error: {message}', [
                'email' => $message->key,
                'message' => $e->getMessage()
            ]);
            Craft::error($error, __METHOD__);
        }
        //   Craft::dd($htmlBody); 
        return;
    }
}
