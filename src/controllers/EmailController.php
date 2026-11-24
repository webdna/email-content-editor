<?php

namespace webdna\craftemailcontenteditor\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use webdna\craftemailcontenteditor\EmailContentEditor;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Email controller
 */
class EmailController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * email-content-editor/email action
     */
    public function actionSendEmail(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('testEmails');
        $id = $this->request->getBodyParam('elementId', null);
        $siteId = $this->request->getBodyParam('siteId',null);

        if (!$id) {
            return $this->asFailure(
                "No entry id in request"
            );
        }
        if (!$siteId) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        $emails = EmailContentEditor::getInstance()->emails;
        $entry = Entry::find()->id($id)->siteId($siteId)->one();
        if (!$entry || !$emails->getEmailSettingsFieldHandle($entry)) {
            return $this->asFailure(
                "Email entry not found"
            );
        }

        // The "Send Test Emails" permission isn't tied to a section, so check this person can see the entry too
        if (!Craft::$app->getElements()->canView($entry, Craft::$app->getUser()->getIdentity())) {
            throw new ForbiddenHttpException('User is not authorized to view this entry.');
        }

        if (!$emails->sendTestEmail($entry->id, $entry->siteId)) {
            return $this->asFailure(
                "Unable to send email"
            );
        }

        Craft::$app->getSession()->setNotice("Email sent successfully");
        return $this->asSuccess(
            "Email sent successfully"
        );
    }
}
