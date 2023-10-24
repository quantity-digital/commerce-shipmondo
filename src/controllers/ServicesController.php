<?php

namespace QD\commerce\shipmondo\controllers;

use craft\web\Controller;

use craft\commerce\Plugin as Commerce;
use QD\commerce\shipmondo\Shipmondo;
use yii\web\Response;

class ServicesController extends Controller
{
    // Public Methods
    // =========================================================================

    public  array|int|bool $allowAnonymous = true;

    /**
     * Get service points for order
     *
     * @return \yii\web\Response
     */
    public function actionListServicePoints(): Response
    {
        //Get active cart
        $order = Commerce::getInstance()->getCarts()->getCart();

        //Pass order to service points service, which will return service points as array
        return $this->asJson(Shipmondo::getInstance()->getServicePoints()->getServicePointsForOrder($order));
    }

    /**
     * Return servicepoints based on the carrier, destination country and postal code
     *
     * @return \yii\web\Response
     */
    public function actionListServicePointsByParams(): Response
    {
        $params = [
            'carrier_code' => $this->request->getParam('carrierCode'),
            'country_code' => $this->request->getParam('countryCode'),
            'zipcode' => $this->request->getParam('postalCode'),
        ];

        return $this->asJson(Shipmondo::getInstance()->getServicePoints()->getServicePointsByParams($params, 20));
    }
}
