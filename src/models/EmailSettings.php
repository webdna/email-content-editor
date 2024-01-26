<?php

namespace mikeymeister\craftemailentries\models;

use Craft;
use craft\base\Model;
use craft\commerce\elements\Order;
use craft\elements\User;
use craft\models\SystemMessage;

/**
 * Email Settings model
 */
class EmailSettings extends Model
{
    public string $messageKey = '';
    public string $subject = '';
    public string $testVariables = '';
    public ?int $testUserId = null;
    public array $testOrderId = [];

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['systemMessageKey','subject','testVariables'],'string']
        ]);
    }

    public function getTestUser(): ?User
    {

        $user = null;
        if ($this->testUserId) {
            $user = User::find()->id($this->testUserId)->one();
        }

        if (!$user) {
            return Craft::$app->getUser()->getIdentity();
        }
    }

    public function getTestOrder(): ?\craft\commerce\elements\Order
    {
        $order = null;
        if (!empty($this->testOrderId)) {
            $order = Order::find()->id($this->testOrderId)->one();
        }

        return $order;
    }

    public function getSystemMessage(): ?SystemMessage
    {
        if (!$this->messageKey) {
            return null;
        }
        $systemMessage = null;
        if (
            str_contains($this->messageKey, 'commerceEmail')
            && Craft::$app->plugins->isPluginEnabled('commerce')) 
            {
            $commerceEmails = \craft\commerce\Plugin::getInstance()->getEmails()->getAllEmails();
            if ($commerceEmails) {
                foreach ($commerceEmails as $commerceEmail) {
                    if ($this->messageKey == 'commerceEmail'.$commerceEmail->id) {
                        # code...
                        $systemMessage = $commerceEmail;
                    }
                }
            }
        } else {
            foreach(Craft::$app->getSystemMessages()->getAllMessages() as $message) {
                if ($message['key'] == $this->key) {
                    $systemMessage = $message['key'];
                }
            }
        }

        return $systemMessage;
    }

}
