<?php

namespace QD\commerce\shipmondo\controllers;

use craft\web\Controller;

use craft\commerce\Plugin as Commerce;
use QD\commerce\shipmondo\Shipmondo;

class ServicesController extends Controller
{
	// Public Methods
	// =========================================================================

    public  array|int|bool $allowAnonymous = true;

	public function actionListServicePoints()
	{
		$order = Commerce::getInstance()->getCarts()->getCart();
		return $this->asJson(Shipmondo::getInstance()->getServicePoints()->getServicePointsForOrder($order));
	}
}
