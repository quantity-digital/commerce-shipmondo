<?php

/**
 * Event Set Shipping Address
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\events;

use craft\commerce\elements\Order;
use yii\base\Event;
use craft\elements\Address;

class SetShippingAddress extends Event
{
	/**
	 * Craft Commerce order
	 *
	 * @var Order
	 */
	public Order $order;

	/**
	 * Craft Address
	 *
	 * @var Address
	 */
	public Address $address;

	/**
	 * Receiver
	 *
	 * @var array
	 */
	public array $receiver;
}
