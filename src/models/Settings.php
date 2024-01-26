<?php

namespace mikeymeister\craftemailentries\models;

use Craft;
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
