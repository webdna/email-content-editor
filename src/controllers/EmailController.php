<?php

namespace webdna\craftemailentries\controllers;

use webdna\craftemailentries\EmailEntries;

use Craft;
use craft\web\Controller;

use yii\web\Response;

/**
 * Email controller
 */
class EmailController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * email-entries/email action
     */
    public function actionSendEmail(): ?Response
    {
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
        if (!Craft::$app->getUser()->checkPermission('testEmails')) {
            return $this->asFailure(
                "User does not have sufficient priviledges to send test email."
            );
        } else {
            $sent = EmailEntries::getInstance()->emails->sendTestEmail($id);
            if ($sent) {
                Craft::$app->getSession()->setNotice("Email sent successfully");
                return $this->asSuccess(
                    "Email sent successfully"
                );
            } else {
                return $this->asFailure(
                    "Unable to send email"
                );
            };
        }

        return $this->redirectToPostedUrl();
    }
}
