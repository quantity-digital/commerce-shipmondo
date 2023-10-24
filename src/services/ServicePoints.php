<?php

namespace QD\commerce\shipmondo\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use Exception;
use QD\commerce\shipmondo\Shipmondo;

class ServicePoints extends Component
{
    /**
     * Get service points from order address
     *
     * @param \craft\commerce\elements\Order $order
     * @param boolean $includeAddress
     * @param integer $quantity
     *
     * @return array
     */
    public function getServicePointsForOrder(Order $order, $includeAddress = false, $quantity = 20): array
    {
        //Get carrier code and shipping address
        $carrierCode = $order->getShippingMethod()->getCarrierCode();
        $shippingAddress = $order->getShippingAddress();

        //Create search array for the servicepoints.
        $params = [
            'carrier_code' => $carrierCode,
            'country_code' => $shippingAddress->countryCode,
            'zipcode' => $shippingAddress->postalCode,
            'quantity' => $quantity
        ];

        //Add address to search array if needed. This will give a more precise result.
        if ($includeAddress) {
            $params['address'] = $shippingAddress->addressLine1;
            $params['city'] = $shippingAddress->city;
        }

        return Shipmondo::getInstance()->getShipmondoApi()->getServicePoints($params)->getOutput();
    }

    /**
     * Get service points from custom search array
     *
     * @param array $params
     * @param integer $quantity
     *
     * @return array
     */
    public function getServicePointsByParams(array $params, int $quantity = 20): array
    {
        //Set numver of service points to return
        $params['quantity'] = $quantity;
        return Shipmondo::getInstance()->getShipmondoApi()->getServicePoints($params)->getOutput();
    }

    /**
     * Get service point by id
     *
     * @param [type] $id
     * @param \craft\commerce\elements\Order $order
     *
     * @return array
     */
    public function getServicePointById($id, Order $order): array
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

    /**
     * Get service point by carriercode and countrycode
     *
     * @param [type] $id
     * @param [type] $carrierCode
     * @param [type] $countryCode
     *
     * @return array
     */
    public function getServicePointByData($id, $carrierCode, $countryCode): array
    {
        $params = [
            'carrier_code' => $carrierCode,
            'country_code' => $countryCode,
            'id' => $id,
        ];

        return Shipmondo::getInstance()->getShipmondoApi()->getServicePoints($params)->getOutput();
    }
}
