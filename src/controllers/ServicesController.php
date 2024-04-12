<?php

namespace QD\commerce\shipmondo\controllers;

use craft\web\Controller;

use craft\commerce\Plugin as Commerce;
use QD\commerce\shipmondo\Shipmondo;
use yii\web\Response;

class ServicesController extends Controller
{
    public $allowAnonymous = true;

    /**
     * Get service points for order
     *
     * @return \yii\web\Response
     */
    public function actionListServicePoints(): Response
    {
        $params = $this->request->getQueryParams();

        //Pass order to service points service, which will return service points as array
        return $this->asJson(Shipmondo::getInstance()->getServicePoints()->getServicePoints($params));
    }
}
