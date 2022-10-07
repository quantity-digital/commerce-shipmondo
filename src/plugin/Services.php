<?php

namespace QD\commerce\shipmondo\plugin;

use QD\commerce\shipmondo\services\OrderInfos;
use QD\commerce\shipmondo\services\Orders;
use QD\commerce\shipmondo\services\ServicePoints;
use QD\commerce\shipmondo\services\ShipmondoApi;
use QD\commerce\shipmondo\services\Units;
use QD\commerce\shipmondo\services\Webhooks;

trait Services
{
	/**
	 * Initializes all service components
	 *
	 * @return void
	 */
	private function initComponents()
	{
		$this->setComponents([
			'shipmondoApi' => ShipmondoApi::class,
			'webhooks' => Webhooks::class,
			'orders' => Orders::class,
			'orderInfos' => OrderInfos::class,
			'units' => Units::class,
			'servicePoints' => ServicePoints::class
		]);
	}

	/**
	 * Returns the Shipmondo API service
	 *
	 * @return \QD\commerce\shipmondo\services\ShipmondoApi
	 */
	public function getShipmondoApi(): ShipmondoApi
	{
		return $this->get('shipmondoApi');
	}

	/**
	 * Returns Webhooks Serice
	 *
	 * @return \QD\commerce\shipmondo\services\Webhooks
	 */
	public function getWebhooks(): Webhooks
	{
		return $this->get('webhooks');
	}

	/**
	 * Returns Orders Service
	 *
	 * @return \QD\commerce\shipmondo\services\Orders
	 */
	public function getOrders(): Orders
	{
		return $this->get('orders');
	}

	/**
	 * Returns Orderinfos Service
	 *
	 * @return \QD\commerce\shipmondo\services\OrderInfos
	 */
	public function getOrderInfos(): OrderInfos
	{
		return $this->get('orderInfos');
	}

	/**
	 * Returns units servoces
	 *
	 * @return \QD\commerce\shipmondo\services\Units
	 */
	public function getUnits(): Units
	{
		return $this->get('units');
	}

	public function getServicePoints(): ServicePoints
	{
		return $this->get('servicePoints');
	}
}
