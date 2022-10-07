<?php

/**
 * Controller for webhooks
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\helpers\Json;
use craft\web\Controller;
use QD\commerce\shipmondo\helpers\Log;
use QD\commerce\shipmondo\Shipmondo;

class WebhooksController extends Controller
{

	// Disable CSRF validation for the entire controller
	public $enableCsrfValidation = false;
	public  array|int|bool $allowAnonymous = true;

	// Public Methods
	// =========================================================================

	public function actionOrderUpdateStatus()
	{
		$this->requirePostRequest();
		$request = Craft::$app->getRequest();
		$bodyParams = $request->getBodyParams();
		$token = $bodyParams['data'];

		$data = Shipmondo::getInstance()->getWebhooks()->decodeWebhook($token);
		if (isset($data['action']) && $data['action'] == 'connection_test') {
			return;
		}

		$shipmondoStatus = $data['order_status'];
		$orderReference = $data['order_id'];

		$order = Order::find()->reference($orderReference)->one();
		if (!$order) {
			return $this->asFailure('No order found');
		}

		$settings = Shipmondo::getInstance()->getSettings();
		if ($settings->canChangeOrderStatus()) {
			foreach ($settings->orderStatusMapping as $mapping) {
				if ($mapping['shipmondo'] == $shipmondoStatus) {
					$orderStatus = Commerce::getInstance()->getOrderStatuses()->getOrderStatusByHandle($mapping['craft']);
					if (!$orderStatus) {
						throw new \RuntimeException("Order status '{$mapping['craft']}' not found in Craft.");
					}

					$order->orderStatusId = $orderStatus->id;
					$order->message = \Craft::t('commerce-shipmondo', "[Shipmondo] Status updated via webhook ({status})", ['status' => $shipmondoStatus]);
					Craft::$app->getElements()->saveElement($order);
				}
			}
		}
	}
}
