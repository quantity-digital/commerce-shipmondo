<?php

/**
 * Service to handle all order related actions
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\helpers\Db;
use QD\commerce\shipmondo\records\OrderInfo;

class OrderInfos extends Component
{
    /**
     * Get shipmondo orderinfo stored in database from craft\commerce\elements\Order
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return OrderInfo
     */
    public function getOrderInfoByOrder(Order $order): OrderInfo
    {
        return $this->getOrderInfoByOrderId($order->id);
    }

    /**
     * Get shipmondo orderinfo stored in database from orderId
     *
     * @param string $orderId
     *
     * @return OrderInfo
     */
    public function getOrderInfoByOrderId(string $orderId): OrderInfo
    {
        $record = OrderInfo::find()->where(['id' => $orderId])->one();;

        return $record ? $record : new OrderInfo(['id' => $orderId]);
    }

    /**
     * Save shipmondoId to database
     *
     * @param \craft\commerce\elements\Order $order
     * @param array $orderInfo
     *
     * @return void
     */
    public function saveShipmondoId($orderId, $shipmondoId)
    {
        //Get order info record
        $record = $this->getOrderInfoByOrderId($orderId);

        //Set shipmondoId and datePushed
        $record->shipmondoId = $shipmondoId;
        $record->datePushed = Db::prepareDateForDb(time());

        //Save record
        $record->save();

        return true;
    }

    /**
     * Save shipmentId to database
     *
     * @param [type] $orderId
     * @param [type] $shipmentId
     *
     * @return boolean
     */
    public function saveShipmentId($orderId, $shipmentId): bool
    {
        //Get order info record
        $record = $this->getOrderInfoByOrderId($orderId);

        //Set shipmentId and datePushed
        $record->shipmentId = $shipmentId;
        $record->datePushed = Db::prepareDateForDb(time());

        //Save record
        $record->save();

        return true;
    }
}
