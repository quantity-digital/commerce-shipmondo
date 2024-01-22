<?php

/**
 * Controller for webhooks
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\controllers;

use Craft;
use craft\commerce\elements\Order;
use QD\commerce\shipmondo\Shipmondo;
use craft\commerce\Plugin as Commerce;
use craft\helpers\Json;
use Exception;
use QD\commerce\shipmondo\helpers\Log;
use QD\commerce\shipmondo\queue\jobs\SyncOrders;
use yii\web\Controller;

class WebhooksController extends Controller
{

    // Disable CSRF validation for the entire controller
    public $enableCsrfValidation = false;
    public  array|int|bool $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    public function actionOrderUpdateStatus()
    {
        //? Shipmondo Statuses
        /**
         * open
         * processing
         * packed
         * cancelled
         * on_hold
         * sent
         * picked_up
         * archived
         * ready_for_pickup
         * released
         */

        //Get token from request
        $request = Craft::$app->getRequest();
        $bodyParams = $request->getBodyParams();

        if (!isset($bodyParams['data'])) {
            throw new Exception('No data in request');
        }

        //Get JWT token
        $token = $bodyParams['data'];

        //Payload
        $payload = Shipmondo::getInstance()->getWebhooks()->decodeWebhook($token);

        //If payload data is not in JSON format, try to decode it
        $data = \is_string($payload['data']) ? Json::decode($payload['data']) : $payload['data'];

        //Check if it is a connection test
        if (isset($payload['action']) && $payload['action'] == 'connection_test') {
            return;
        }

        //Request doesn't contain a order id, return as failure
        if (!isset($data['order_id']) || $data['order_id'] == '') {
            throw new Exception('No order ID in request');
        }

        //Request doesn't contain a order status, return as failure
        if (!isset($data['order_status']) || $data['order_status'] == '') {
            throw new Exception('No order status in request');
        }

        //Get order status and reference
        $shipmondoStatus = $data['order_status'];
        $orderReference = $data['order_id'];

        //Get order
        $order = Order::find()->reference($orderReference)->one();

        //If order is not found and status is cancelled, return as success
        if (!$order && $shipmondoStatus == 'cancelled') {
            return;
        }

        //No order found, return as failure
        if (!$order) {
            throw new Exception('Order with reference ' . $orderReference . ' not found.');
        }

        return Shipmondo::getInstance()->getStatusService()->changeOrderStatusFromShipmondoHandle($order, $shipmondoStatus);
    }
}
