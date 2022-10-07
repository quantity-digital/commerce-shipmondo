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
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use QD\commerce\shipmondo\records\OrderInfo;

class OrderInfos extends Component
{
	public function getOrderInfoByOrder(Order $order)
	{
		return $this->getOrderInfoByOrderId($order->id);
	}

	public function getOrderInfoByOrderId(string $orderId): OrderInfo
	{
		$record = OrderInfo::find()->where(['id' => $orderId])->one();;

		return $record ? $record : new OrderInfo(['id' => $orderId]);
	}

	public function saveShipmondoId($orderId, $shipmondoId)
	{
		$now = Db::prepareDateForDb(DateTimeHelper::now());
		$record = $this->getOrderInfoByOrderId($orderId);
		$record->shipmondoId = $shipmondoId;
		$record->datePushed = $now;
		$record->save();

		return true;
	}
}
