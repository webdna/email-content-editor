<?php

namespace webdna\craftemailcontenteditor\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use webdna\craftemailcontenteditor\EmailContentEditor;
use webdna\craftemailcontenteditor\fields\EmailSettings as EmailSettingsField;
use webdna\craftemailcontenteditor\models\EmailSettings as EmailSettingsModel;
use yii\console\ExitCode;

/**
 * Checks email content against the Twig sandbox.
 */
class SandboxController extends Controller
{
    public $defaultAction = 'check';

    /**
     * Test-renders every email entry's subject and content in the sandbox and lists anything it blocks.
     *
     * Each entry is rendered with its test user, test order and test variables. Only Twig that runs with that
     * test data is checked: anything inside an `{% if %}` that's false for the test data isn't, so choose
     * test data that exercises the whole email.
     *
     * Exits with an error code if any entry has problems, so it can be used in deployment checks.
     */
    public function actionCheck(): int
    {
        $sites = Craft::$app->getSites();
        $originalSite = $sites->getCurrentSite();
        $checked = 0;
        $failed = 0;

        foreach ($this->_emailEntries() as $entry) {
            $checked++;
            $label = sprintf('%s (entry %s, site "%s")', $entry->title, $entry->id, $entry->getSite()->handle);

            $sites->setCurrentSite($entry->getSite());
            try {
                $problems = $this->_check($entry);
            } finally {
                $sites->setCurrentSite($originalSite);
            }

            if ($problems) {
                $failed++;
                $this->stdout("✗ $label\n", Console::FG_RED);
                foreach ($problems as $problem) {
                    $this->stdout("    $problem\n");
                }
            } else {
                $this->stdout("✓ $label\n", Console::FG_GREEN);
            }
        }

        $this->stdout(sprintf("\nChecked %d email %s: %d with problems.\n", $checked, $checked === 1 ? 'entry' : 'entries', $failed));

        return $failed ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * @return Entry[] Every entry with email settings, in every site, enabled or not
     */
    private function _emailEntries(): array
    {
        $entries = [];
        foreach (Craft::$app->getFields()->getFieldsByType(EmailSettingsField::class) as $field) {
            $query = Entry::find()->site('*')->status(null);
            $query->{$field->handle}(':notempty:');
            foreach ($query->all() as $entry) {
                $entries["$entry->id-$entry->siteId"] = $entry;
            }
        }

        return array_values($entries);
    }

    /**
     * @return string[] Problems found
     */
    private function _check(Entry $entry): array
    {
        $emails = EmailContentEditor::getInstance()->emails;
        $problems = [];

        try {
            $fieldHandle = $emails->getEmailSettingsFieldHandle($entry);
            $emailSettings = new EmailSettingsModel($entry->getFieldValue($fieldHandle));
            $variables = $emails->mergeTestVariables($emailSettings, ['entry' => $entry], false);
        } catch (\Throwable $e) {
            return ['Couldn’t load the email settings: ' . $e->getMessage()];
        }

        try {
            $emails->sandboxRender($emailSettings->subject, $variables);
        } catch (\Throwable $e) {
            $problems[] = 'Subject: ' . $e->getMessage();
        }

        try {
            $html = $emails->renderEntryTemplate($entry, $variables);
        } catch (\Throwable $e) {
            $problems[] = 'Site template (not affected by the sandbox): ' . $e->getMessage();
            return $problems;
        }

        if ($html === null) {
            $this->stdout("  No site template for this entry's site, so its content wasn't checked.\n", Console::FG_YELLOW);
            return $problems;
        }

        try {
            $emails->sandboxRender($html, $variables);
        } catch (\Throwable $e) {
            $problems[] = 'Content: ' . $e->getMessage();
        }

        return $problems;
    }
}
