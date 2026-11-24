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

    /**
     * Additions to what email content can use in the Twig sandbox. See `config.php` for the format.
     *
     * Keys: `allowedTags`, `allowedFilters`, `allowedFunctions` (lists of names), and `allowedProperties`
     * and `allowedMethods` (class name => list of names).
     */
    public array $sandbox = [];

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }
}
