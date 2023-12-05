<?php

namespace mikeymeister\craftemailentries\services;

use mikeymeister\craftemailentries\models\Email;
use mikeymeister\craftemailentries\records\Email as EmailRecord;


use Craft;
use craft\elements\Entry;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\App;
use craft\helpers\DateTimeHelper;
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
    public function getEmailById(int $id): ?Email
    {
        $query = $this->_createEmailQuery()
            ->andWhere(Db::parseParam('emails.id',$id))
            ->one();

            if (!$query) {
            return null;
        }
        $email = new Email($query);
        return $email;
    }

    public function getEmailByKey(string $key): ?Email
    {
        $query = $this->_createEmailQuery()
            ->andWhere(Db::parseParam('emails.systemMessageKey',$key))
            ->one();

        if (!$query) {
            return null;
        }
        $email = new Email($query);
        return $email;
    }

    public function getEmailByEntryId(int $id, int $siteId): ?Email
    {
        // get the entry
        $entry = Entry::find()
            ->id($id)
            ->siteId($siteId ?? '*')
            ->one();
        
        $query = $this->_createEmailQuery()
            ->andWhere(Db::parseParam('emails.elementId',$entry->id))
            ->andWhere(Db::parseParam('emails.siteId', $siteId ?? $entry->siteId))
            ->one();

        if (!$query) {
            return null;
        }
        $email = new Email($query);
        return $email;
    }

    public function getEmailByEntry(Entry $entry): ?Email
    {
        $query = $this->_createEmailQuery()
            ->andWhere(Db::parseParam('emails.elementId', $entry->id))
            ->andWhere(Db::parseParam('emails.siteId', $entry->siteId))
            ->one();

        if (!$query) {
            return null;
        }
        $email = new Email($query);
        return $email;
    }

    public function getAllMessages(): array
    {
        $emails = [];
        $systemEmails = Craft::$app->getSystemMessages()->getAllMessages();
        foreach ($systemEmails as $systemEmail) {
            $emails[$systemEmail->key] = ucwords(str_replace('_',' ',$systemEmail->key));
        }
        $emails += $this->getAllCommerceEmails();
        return $emails;
    }

    public function getAllCommerceEmails(): array
    {
        $emails = [];
        if (Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
            $commerceEmails = \craft\commerce\Plugin::getInstance()->getEmails()->getAllEmails();
            if ($commerceEmails) {
                foreach ($commerceEmails as $commerceEmail) {
                    $emails['commerceEmail'.$commerceEmail->id] = $commerceEmail->name;
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

    public function saveEmailOnEntrySave($entry): bool 
    {
        $request = Craft::$app->getRequest();
                    
        $email = $this->getEmailByEntry($entry);

        if (!$email) {
            $email = new Email();
            $email->siteId = $entry->siteId;
            $email->elementId = $entry->id;
        }
        $email->systemMessageKey = $request->getBodyParam('system-message',$email->systemMessageKey);
        $email->subject = $request->getBodyParam('subject',$email->subject );
        $testVariables = $request->getBodyParam('testVariables', $email->testVariables);
        if ($testVariables && !Json::isJsonObject($testVariables)) {
            $testVariables = '';
        }
        $email->testVariables = $testVariables;
        // Craft::dd($request->getBodyParams());
        return $this->saveEmail($email);
    }

    public function saveEmail($model): bool
    {
        if ($model->id) {
            $record = EmailRecord::findOne($model->id);
            if (!$record) {
                $record = new EmailRecord();            
                $record->id = $model->id;
            } 
        } elseif ($model->elementId) {
            $record = EmailRecord::findOne(['elementId' => $model->elementId]);
            if (!$record) {
                $record = new EmailRecord();
                $record->elementId = $model->elementId;
            }
        } else {
            $record = new EmailRecord(); 
        }

        $record->siteId = $model->siteId;
        $record->elementId = $model->elementId;
        $record->systemMessageKey = $model->systemMessageKey;
        $record->subject =  $model->subject;
        $record->testVariables = $model->testVariables ?? $record->testVariables ?? '';
        $record->save();
        return true;
    }
    
    public function sendTestEmail($user, $id): bool
    {   
		$settings = App::mailSettings();
        $entry = Entry::find()->id($id)->one();
        $email = $this->getEmailById($id);
        
        if (!$email) {
            $email = new Email();
            $email->elementId = $entry->id;
            $email->systemMessageKey = Craft::$app->getRequest()->getBodyParam('system-message');
            $email->subject = Craft::$app->getRequest()->getBodyParam('subject','');
            $testVariables = Craft::$app->getRequest()->getBodyParam('testVariables','');
            if ($testVariables && !Json::isJsonObject($testVariables)) {
                $testVariables = '';
            }
            $email->testVariables = $testVariables;
            if(!$this->saveEmail($email)) {
                return false;
            }
        }

        $variables['entry'] = $entry;
        $variables['recipient'] = $user;

        if ($email->testVariables) {
            $rendered = Craft::$app->getView()->renderString($email->testVariables, $variables, Craft::$app->getView()::TEMPLATE_MODE_SITE);
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

        $message = $this->buildEmail($entry,$message,$email,$variables);

        if ($message == false){   
            return false;
        } else {
            Craft::$app->mailer->send($message);
            return true;
        }
    }

    public function buildEmail(Entry $entry, Message $message, Email $email, Array $variables): mixed
    {
        if (!$this->_createSubjectLine($email,$variables,$message)) {
            return false;
        }
        
        if (!$this->_createBody($entry,$variables,$message,$email)) {
            return false;
        }
        return $message;
    }


    // Private Methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving Emails.
     *
     * @return Query
     */
    private function _createEmailQuery(): Query
    {
        $now = DateTimeHelper::currentUTCDateTime();
        return (new Query())
            ->select([
                'emails.id',
                'emails.systemMessageKey',
                'emails.subject',
                'emails.testVariables',
                'emails.elementId',
                'emails.siteId'
            ])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[emails.elementId]] = [[elements.id]]')
            ->innerJoin(['elements_sites' => Table::ELEMENTS_SITES], '[[emails.elementId]] = [[elements_sites.elementId]]')
            ->innerJoin(['entries' => Table::ENTRIES], '[[emails.elementId]] = [[entries.id]]')
            ->where(Db::parseParam('elements_sites.siteId',Craft::$app->getSites()->currentSite->id))
            ->andWhere(Db::parseParam('elements_sites.enabled',1))
            ->andWhere(Db::parseParam('elements.dateDeleted',':empty:'))
            ->andWhere(Db::parseDateParam('entries.postDate', $now, '<='))
            ->andWhere(['or',Db::parseDateParam('entries.expiryDate', $now, '>'),Db::parseDateParam('entries.expiryDate', ':empty:')])
            ->orderBy('entries.postDate')
            ->from(['{{%email-entries_emails}} emails']);
    }

    private function _createSubjectLine($email,$variables,$message): bool
    {
        $view = Craft::$app->getView();
        $subject = $view->renderString($email->subject, $variables, $view::TEMPLATE_MODE_SITE);

        try {
            $message->setSubject($subject);
        } catch (\Exception $e) {
            $error = Craft::t('email-entries', 'Email template parse error for email “{email}” in “Subject:”. To: “{to}”. Template error: “{message}”', [
                'email' => $message->key,
                'to' => $message->getTo(),
                'message' => $e->getMessage()
            ]);
            Craft::error($error, __METHOD__);
            return false;
        }
            
        return true;
    }

    private function _createBody($entry,$variables,$message,$email): bool
    {
        $view = Craft::$app->getView();
        $siteSettings = Craft::$app->getSections()->getSectionSiteSettings($entry->sectionId);
        foreach ($siteSettings as $setting) {
            if ($setting['siteId'] == Craft::$app->getSites()->getCurrentSite()->id ) {
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
                    'email' => $email->systemMessageKey,
                    'message' => $e->getMessage()
                ]);
            }
            $message->setHtmlBody($htmlBody);
        } catch (\Exception $e) {
            $error = Craft::t('email-entries', 'Email template parse error for email {email}. Failed to set bodyHtml. Template error: {message}', [
                'email' => $email->systemMessageKey,
                'message' => $e->getMessage()
            ]);
            return false;
        }
           
        return true;
    }
}
