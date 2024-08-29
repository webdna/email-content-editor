<?php

namespace webdna\craftemailcontenteditor\models;

use Craft;
use craft\base\Model;
use craft\elements\User;
use webdna\craftemailcontenteditor\EmailContentEditor;

/**
 * Email Settings model
 */
class Recipient extends Model
{
    public string $email = '';
    public string $firstName = '';
    public string $lastName = '';
    public string $friendlyName = '';
    public array $customFields = [];
    private User|null $_user = null;

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['email','firstName','lastName', 'friendlyName'],'string'],
        ]);
    }

    public function __construct(User|array $config = [])
    {
        if ($config instanceof User) {
            $recipient = [
                'email' => $config->email,
                'firstName' => $config->firstName,
                'lastName' => $config->lastName,
                'friendlyName' => $config->friendlyName,
            ];
        } else {
            $recipient = $config;
        }

        parent::__construct($recipient);

        if ($config instanceof User) {
            $this->setUser($config);
            $this->setCustomFields();
        }
    }

    public function setUser(User $user) {
        if ($user) {
            $this->_user = $user;
        }
    }

    public function setCustomFields()
    {
        if ($this->_user) {
            $allowedCustomFields = EmailContentEditor::getInstance()->getSettings()->recipientFields;
            $customFields = $this->_user->getFieldValues($allowedCustomFields);
            foreach ($customFields as $key => $value) {
               $this->customFields[$key] = $value;
            }
        }
    }



}
