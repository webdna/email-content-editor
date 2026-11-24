<?php

namespace webdna\craftemailcontenteditor\models;

use craft\base\Model;
use craft\elements\User;
use craft\web\twig\AllowedInSandbox;
use webdna\craftemailcontenteditor\EmailContentEditor;

/**
 * The person an email is being sent to, as made available to email content as `recipient`.
 *
 * Only the properties marked #[AllowedInSandbox] can be read from email content.
 */
class Recipient extends Model
{
    #[AllowedInSandbox]
    public string $email = '';
    #[AllowedInSandbox]
    public string $firstName = '';
    #[AllowedInSandbox]
    public string $lastName = '';
    #[AllowedInSandbox]
    public string $friendlyName = '';
    /** The recipient's custom field values, for the fields listed in the `recipientFields` setting. */
    #[AllowedInSandbox]
    public array $customFields = [];
    private User|null $_user = null;

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['email', 'firstName', 'lastName', 'friendlyName'], 'string'],
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

    public function setUser(User $user)
    {
        if ($user) {
            $this->_user = $user;
        }
    }

    public function setCustomFields()
    {
        if ($this->_user) {
            $allowedCustomFields = EmailContentEditor::getInstance()->getSettings()->recipientFields;
            if (!empty($allowedCustomFields)) {
                $customFields = $this->_user->getFieldValues($allowedCustomFields);
                foreach ($customFields as $key => $value) {
                    $this->customFields[$key] = $value;
                }
            }
        }
    }
}
