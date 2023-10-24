<?php

namespace QD\commerce\shipmondo\controllers;

use Craft;
use craft\web\Controller;

use craft\commerce\Plugin as Commerce;
use QD\commerce\shipmondo\Shipmondo;
use yii\web\Response;

class SettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the settings template
     *
     * @return void
     */
    public function actionIndex()
    {
        $settings = Shipmondo::$plugin->getSettings();

        return $this->renderTemplate('commerce-shipmondo/settings', array(
            'settings' => $settings,
            'allowAdminChanges' => Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
        ));
    }

    /**
     * Fetches all available shipping templates from Shipmondo
     *
     * @return \yii\web\Response
     */
    public function actionGetShippingTemplates(): Response
    {
        $templates = Shipmondo::getInstance()->getShipmondoApi()->getShipmentTemplates([
            'per_page' => "500"
        ])->getOutput();
        return $this->asJson($templates);
    }
}
