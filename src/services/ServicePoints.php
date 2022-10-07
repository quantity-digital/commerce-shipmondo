<?php

namespace QD\commerce\shipmondo\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use Exception;
use QD\commerce\shipmondo\Shipmondo;

class ServicePoints extends Component
{
	public function getServicePointsForOrder(Order $order, $includeAddress = false, $quantity = 20): array
	{
		$carrierCode = $order->getShippingMethod()->getCarrierCode();
		$shippingAddress = $order->getShippingAddress();

		$params = [
			'carrier_code' => $carrierCode,
			'country_code' => $shippingAddress->countryCode,
			'zipcode' => $shippingAddress->postalCode,
			'quantity' => $quantity
		];

		if ($includeAddress) {
			$params['address'] = $shippingAddress->addressLine1;
			$params['city'] = $shippingAddress->city;
		}

		return Shipmondo::getInstance()->getShipmondoApi()->getServicePoints($params)->getOutput();
	}

	public function getServicePointById($id, $order)
	{
		$carrierCode = $order->getShippingMethod()->getCarrierCode();
		$shippingAddress = $order->getShippingAddress();

		$params = [
			'carrier_code' => $carrierCode,
			'country_code' => $shippingAddress->countryCode,
			'id' => $id,
		];

		return Shipmondo::getInstance()->getShipmondoApi()->getServicePoints($params)->getOutput();
	}
}
