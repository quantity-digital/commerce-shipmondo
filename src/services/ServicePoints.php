<?php

namespace QD\commerce\shipmondo\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use QD\commerce\shipmondo\Shipmondo;
use craft\commerce\Plugin as Commerce;
use Exception;

class ServicePoints extends Component
{
    public function getServicePoints(array $queryParams): array
    {
        $includeAddress = $queryParams['includeAddress'] ?? false;
        $quantity = $queryParams['quantity'] ?? 20;

        if (!$this->_hasRequiredParams($queryParams)) {
            $order = Commerce::getInstance()->getCarts()->getCart();
            $params = $this->getServicePointParamsFromOrder($order, $includeAddress, $quantity);
            return Shipmondo::getInstance()->getShipmondoApi()->getServicePoints($params)->getOutput();
        }

        if (!$this->_validateRequiredParams($queryParams)) {
            throw new Exception("A value is missing from required params", 1);
        }

        $params = $this->getServicePointParamsFromQuery($queryParams, $includeAddress, $quantity);

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
    public function getServicePointParamsFromOrder(Order $order, bool $includeAddress, int $quantity): array
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

        return $params;
    }

    /**
     * Get available service points from query params
     *
     * @param array $params
     * @param boolean $includeAddress
     * @param integer $quantity
     * @return array
     */
    public function getServicePointParamsFromQuery(array $params, bool $includeAddress, int $quantity): array
    {
        $params = [
            'carrier_code' => $params['carrierCode'],
            'country_code' => $params['countryCode'],
            'zipcode' => $params['postalCode'],
            'quantity' => $quantity
        ];

        if ($includeAddress && $this->_hasAddressParams($params)) {
            $params['address'] = $params['address'];
            $params['city'] = $params['city'];
        }

        return $params;
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
