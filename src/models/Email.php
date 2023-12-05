<?php

namespace mikeymeister\craftemailentries\models;

use Craft;
use craft\base\Model;
use craft\elements\Entry;

/**
 * Email model
 */
class Email extends Model
{
    public ?int $id = null;
    public ?int $siteId = null;
    public ?int $elementId = null;
    public string $systemMessageKey = '';
    public string $subject = '';
    public string $testVariables = '';

    public function getEntry(): ?Entry
    {
        if (!$this->elementId) {
            return false;
        }

        $entry = Entry::find()
            ->siteId($this->siteId)
            ->id($this->elementId)
            ->one();
        
        return $entry ?? null;
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['id'], 'number', 'integerOnly' => true],
            [['systemMessageKey','subject','testVariables'],'string']
        ]);
    }
}
