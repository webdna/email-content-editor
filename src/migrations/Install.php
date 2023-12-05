<?php

namespace mikeymeister\craftemailentries\migrations;

use Craft;
use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $tablesCreated = false;
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%email-entries_emails}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%email-entries_emails}}',
                [
                    'id' => $this->primaryKey(),
                    'elementId' => $this->integer(),
                    'siteId' => $this->integer(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'systemMessageKey' => $this->string(255),
                    'subject' => $this->string(255),
                    'testVariables' => $this->longText()
                ]
            );
        }
        return $tablesCreated;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    protected function createIndexes(): void
    {
        $this->createIndex(null, '{{%email-entries_emails}}', 'id', false);
        $this->createIndex(null, '{{%email-entries_emails}}', 'elementId', false);
        $this->createIndex(null, '{{%email-entries_emails}}', 'systemMessageKey', false);

    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(null, '{{%email-entries_emails}}', ['elementId'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%email-entries_emails}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', null);

    }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables(): void
    {
        $this->dropTableIfExists('{{%email-entries_emails}}');
    }
}
