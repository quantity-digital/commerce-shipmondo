<?php

/**
 * Event after order lines creation
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\events;

use craft\commerce\elements\Order;
use yii\base\Event;

class SetOrderLines extends Event
{
	/**
	 * Craft Commerce order
	 *
	 * @var Order
	 */
	public Order $order;

	/**
	 * Commerce line items
	 *
	 * @var array
	 */
	public array $lineItems;

	/**
	 * Shipmondo orderlines
	 *
	 * @var array
	 */
	public array $orderLines;
}
