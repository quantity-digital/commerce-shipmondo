<?php

/**
 * Event after order lines creation
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\events;

use craft\commerce\elements\Order;
use yii\base\Event;

class ConvertOrder extends Event
{
	/**
	 * Craft Commerce order
	 *
	 * @var Order
	 */
	public Order $order;

	/**
	 * Converted order
	 *
	 * @var Array
	 */
	public $shipmondoOrder;
}
