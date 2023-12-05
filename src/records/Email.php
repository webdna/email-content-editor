<?php

namespace mikeymeister\craftemailentries\records;

use Craft;
use craft\db\ActiveRecord;

/**
 * Email record
 */
class Email extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%email-entries_emails}}';
    }
}
