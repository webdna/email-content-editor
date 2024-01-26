<?php

namespace mikeymeister\craftemailentries\services;

use mikeymeister\craftemailentries\models\Email;
use mikeymeister\craftemailentries\records\Email as EmailRecord;


use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\mail\Message;
use craft\models\SystemMessage;
use mikeymeister\craftemailentries\fields\EmailSettings as FieldsEmailSettings;
use mikeymeister\craftemailentries\models\EmailSettings;
use yii\base\Component;

/**
 * Emails service
 */
class Emails extends Component
{
    public function findEntryForEmail($messageKey): ?Entry
    {
        $fields = $this->getAllEmailSettingsFields();
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

    public function getAllEmailSettingsFields(): array
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
    
    public function sendTestEmail($user, $id): bool
    {   
		$settings = App::mailSettings();
        $entry = Entry::find()->id($id)->one();
        $fieldHandle = $this->getEmailSettingsFieldHandle($entry);
        $emailSettings = $entry->getFieldValue($fieldHandle);
        

        $variables['entry'] = $entry;
        $variables['recipient'] = $user;
        $variables['order'] = $emailSettings['testOrderId'];

        $testVariables = $emailSettings['testVariables'];
        
        if ($testVariables) {
            $rendered = Craft::$app->getView()->renderString($testVariables, $variables, Craft::$app->getView()::TEMPLATE_MODE_SITE);
            $testVariables = Json::decodeIfJson($rendered);
            if ($testVariables) {
                foreach ($testVariables as $key => $value) {
                    $variables[$key] = $value;
                }
            }
        }

        $message = new Message;
        $message->setFrom([App::parseEnv($settings['fromEmail']) => App::parseEnv($settings['fromName'])]);
        $message->setTo($user->email);

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
        $emailSettings = new EmailSettings($entry->getFieldValue($fieldHandle));
        if (!$this->_createSubjectLine($emailSettings->subject,$variables,$message)) {
            return false;
        }
        
        if (!$this->_createBody($entry,$variables,$message)) {
            return false;
        }
        return $message;
    }


    // Private Methods
    // =========================================================================

    private function _createSubjectLine($subject,$variables,$message): bool
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
            return false;
        }
            
        return true;
    }

    private function _createBody($entry,$variables,$message): bool
    {
        $view = Craft::$app->getView();
        $siteSettings = Craft::$app->getSections()->getSectionSiteSettings($entry->sectionId);
        foreach ($siteSettings as $setting) {
            if ($setting['siteId'] == $entry->siteId ) {
                $template = $setting['template'];
            }
        }
        if (!$template) {
            return false;
        }
        
        try {
            $htmlBody = $view->renderTemplate($template, $variables, $view::TEMPLATE_MODE_SITE);
            // Lets double render incase the user has any {variable} stuff in there.
            try {
                $htmlBody = $view->renderString($htmlBody,$variables,$view::TEMPLATE_MODE_SITE);

            } catch (\Exception $e) {
                $error = Craft::t('email-entries', 'Email template parse error for email {email}. Failed to render content variables. Template error: {message}', [
                    'email' => $message['key'],
                    'message' => $e->getMessage()
                ]);
            }
            $message->setHtmlBody($htmlBody);
        } catch (\Exception $e) {
            $error = Craft::t('email-entries', 'Email template parse error for email {email}. Failed to set bodyHtml. Template error: {message}', [
                'email' => $message['key'],
                'message' => $e->getMessage()
            ]);
            return false;
        }
        //   Craft::dd($htmlBody); 
        return true;
    }
}
