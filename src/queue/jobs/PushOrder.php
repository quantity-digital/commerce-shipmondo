<?php

namespace QD\commerce\shipmondo\queue\jobs;

use craft\commerce\elements\Order;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use QD\commerce\shipmondo\Shipmondo;
use white\commerce\sendcloud\SendcloudPlugin;

class PushOrder extends BaseJob
{
	public int $orderId;

	public function execute($queue): void
	{
		$order = Order::find()->id($this->orderId)->status(null)->one();

		if (!$order) {
			return;
		}

		$push = Shipmondo::getInstance()->getOrders()->pushOrder($order);

		if (!$push) {
			Queue::push(new PushOrder([
				'orderId' => $order->id,
			]), 10, 300);
		}
	}

	protected function defaultDescription(): ?string
	{
		return 'Syncing order #' . $this->orderId . ' to Shipmondo';
	}
}
