<?php

namespace QD\commerce\shipmondo\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use QD\commerce\shipmondo\Shipmondo;
use craft\commerce\Plugin as Commerce;
use craft\commerce\models\Address;
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
            'country_code' => $shippingAddress->countryIso,
            'zipcode' => $shippingAddress->zipCode,
            'quantity' => $quantity
        ];

        if (isset($shippingAddress->city) && isset($shippingAddress->address1) && $shippingAddress->city && $shippingAddress->address1) {
            $params['address'] = $shippingAddress->address1;
            $params['city'] = $shippingAddress->city;
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
            'country_code' => $address->countryIso,
            'zipcode' => $address->zipCode,
            'quantity' => $quantity
        ];

        if (isset($address->city) && isset($address->address1) && $address->city && $address->address1) {
            $params['address'] = $address->address1;
            $params['city'] = $address->city;
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
            'country_code' => $shippingAddress->countryIso,
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
