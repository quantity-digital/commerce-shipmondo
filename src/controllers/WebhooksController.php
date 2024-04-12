<?php

/**
 * Controller for webhooks
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\controllers;

use Craft;
use QD\commerce\shipmondo\queue\jobs\UpdateStatusWebhook;
use yii\web\Controller;

class WebhooksController extends Controller
{

    // Disable CSRF validation for the entire controller
    public $enableCsrfValidation = false;
    public $allowAnonymous = true;

    public function actionOrderUpdateStatus()
    {
        // Get request
        $request = Craft::$app->getRequest();

        // Get body params
        $bodyParams = $request->getBodyParams();

        // Start queue job
        Craft::$app->getQueue()->push(new UpdateStatusWebhook(
            [
                'params' => $bodyParams
            ]
        ));

        // Return success
        return true;
    }
}
