<?php

/**
 * Service to handle status related functions
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderStatus;
use QD\commerce\shipmondo\Shipmondo;
use craft\commerce\Plugin as Commerce;
use Exception;

class StatusService extends Component
{
  /**
   * Function to return the craft status handle corrosponding to a shipmondo status
   *
   * @param string $shipmondoHandle
   * @return string
   */
  public function getOrderStatusHandleFromShipmondoHandle(string $shipmondoStatus): string
  {

    // Get Shipmondo settings
    $settings = Shipmondo::getInstance()->getSettings();

    // Check if status mapping is enabled
    if (!$settings->canChangeOrderStatus()) {
      return '';
    }

    // Get the craft status from the mapping
    foreach ($settings->orderStatusMapping as $mapping) {
      if ($mapping['shipmondo'] == $shipmondoStatus) {
        return $mapping['craft'];
      }
    }

    // If no mapping is found, return empty string
    return '';
  }

  public function getOrderStatusByShipmondoHandle(string $shipmondoHandle, Order $order): ?OrderStatus
  {
    $orderStatusHandle = $this->getOrderStatusHandleFromShipmondoHandle($shipmondoHandle);

    if (!$orderStatusHandle) {
      return null;
      // throw new Exception("Craft status handle matching '{$shipmondoHandle}' not found");
    }

    $orderStatus = Commerce::getInstance()->getOrderStatuses()->getOrderStatusByHandle($orderStatusHandle, $order->storeId);

    if (!$orderStatus) {
      return null;
      // throw new Exception("Craft status matching '{$shipmondoHandle}' not found");
    }

    return $orderStatus;
  }

  public function changeOrderStatusFromShipmondoHandle(string $shipmondoHandle, Order $order)
  {
    $orderStatus = $this->getOrderStatusByShipmondoHandle($shipmondoHandle, $order);

    if (!$orderStatus) {
      // throw new Exception("No order status found for $shipmondoHandle", 1);
      return;
    }

    //Update order status
    $order->orderStatusId = $orderStatus->id;
    $order->message = \Craft::t('commerce-shipmondo', "[Shipmondo] Status updated via webhook ({status})", ['status' => $shipmondoHandle]);
    $save = Craft::$app->getElements()->saveElement($order);

    //Check if order status was saved
    if (!$save) {
      throw new \Exception("Could not save order status");
    }
  }
}
