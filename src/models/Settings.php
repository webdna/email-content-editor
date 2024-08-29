<?php

namespace webdna\craftemailcontenteditor\models;

use craft\base\Model;

/**
 * Settings model
 */
class Settings extends Model
{
    public array $customSystemMessages = [];
    public array $recipientFields = [];
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }
}
