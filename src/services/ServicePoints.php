<?php

namespace QD\commerce\shipmondo\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use QD\commerce\shipmondo\Shipmondo;
use craft\commerce\Plugin as Commerce;
use craft\elements\Address;
use Exception;

class ServicePoints extends Component
{
    public function getServicePoints(array $queryParams): array
    {
        $quantity = $queryParams['quantity'] ?? 20;

        if (!$this->_hasRequiredParams($queryParams)) {
            $order = Commerce::getInstance()->getCarts()->getCart();
            $params = $this->getServicePointParamsFromOrder($order, $quantity);
            return Shipmondo::getInstance()->getShipmondoApi()->getServicePoints($params)->getOutput();
        }

        if (!$this->_validateRequiredParams($queryParams)) {
            throw new Exception("A value is missing from required params", 1);
        }

        $params = $this->getServicePointParamsFromQuery($queryParams, $quantity);

        //Pass order to service points service, which will return service points as array
        return Shipmondo::getInstance()->getShipmondoApi()->getServicePoints($params)->getOutput();
    }


    /**
     * Get available service points from order data
     *
     * @param Order $order
     * @param boolean $includeAddress
     * @param integer $quantity
     * @return array
     */
    public function getServicePointParamsFromOrder(Order $order, int $quantity): array
    {
        $carrierCode = $order->getShippingMethod()->getCarrierCode();
        $shippingAddress = $order->getShippingAddress();

        $params = [
            'carrier_code' => $carrierCode,
            'country_code' => $shippingAddress->countryCode,
            'zipcode' => $shippingAddress->postalCode,
            'quantity' => $quantity
        ];

        if (isset($shippingAddress->locality) && isset($shippingAddress->addressLine1) && $shippingAddress->locality && $shippingAddress->addressLine1) {
            $params['address'] = $shippingAddress->addressLine1;
            $params['city'] = $shippingAddress->locality;
        }

        return $params;
    }

    /**
     * Get available service points from query params
     *
     * @param array $queryParams
     * @param boolean $includeAddress
     * @param integer $quantity
     * @return array
     */
    public function getServicePointParamsFromQuery(array $queryParams, int $quantity): array
    {
        $params = [
            'carrier_code' => $queryParams['carrier_code'],
            'country_code' => $queryParams['country_code'],
            'zipcode' => $queryParams['zipcode'],
            'quantity' => $quantity
        ];

        if ($this->_hasAddressParams($queryParams)) {
            $params['address'] = $queryParams['address'];
            $params['city'] = $queryParams['city'];
        }

        return $params;
    }

    /**
     * Get servicpoints from address
     *
     * @param Address $address
     * @param string $carrierCode
     * @param integer $quantity
     * @return array
     */
    public function getServicePointByAddress(Address $address, string $carrierCode, int $quantity = 20): array
    {
        $params = [
            'carrier_code' => $carrierCode,
            'country_code' => $address->countryCode,
            'zipcode' => $address->postalCode,
            'quantity' => $quantity
        ];

        if (isset($address->locality) && isset($address->addressLine1) && $address->locality && $address->addressLine1) {
            $params['address'] = $address->addressLine1;
            $params['city'] = $address->locality;
        }

        return Shipmondo::getInstance()->getShipmondoApi()->getServicePoints($params)->getOutput();
    }

    /**
     * Get service point by id
     *
     * @param int|string $id
     * @param \craft\commerce\elements\Order $order
     *
     * @return array
     */
    public function getServicePointById(int|string $id, Order $order): array
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

    //* PRIVATE

    /**
     * Check if required params are present
     *
     * @param array $params
     * @return boolean
     */
    private function _hasRequiredParams(array $params): bool
    {
        $requiredParams = ['carrier_code', 'country_code', 'zipcode'];

        foreach ($requiredParams as $requiredParam) {
            if (!array_key_exists($requiredParam, $params)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate if required params have a value
     *
     * @param array $params
     * @return boolean
     */
    private function _validateRequiredParams(array $params): bool
    {
        $requiredParams = ['carrier_code', 'country_code', 'zipcode'];

        foreach ($requiredParams as $requiredParam) {
            if (!isset($params[$requiredParam]) || !$params[$requiredParam]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if address params are present
     *
     * @param array $params
     * @return bool
     */
    private function _hasAddressParams(array $params): bool
    {
        $requiredParams = ['address', 'city'];

        foreach ($requiredParams as $requiredParam) {
            if (!array_key_exists($requiredParam, $params)) {
                return false;
            }
        }

        return true;
    }
}
