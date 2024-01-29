<?php

namespace webdna\craftemailentries\models;

use craft\base\Model;

/**
 * Settings model
 */
class Settings extends Model
{
    public array $customSystemMessages = [];
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }
}
