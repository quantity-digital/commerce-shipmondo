<?php

namespace QD\commerce\shipmondo\queue\jobs;

use craft\commerce\elements\Order;
use craft\helpers\Json;
use craft\queue\BaseJob;
use Exception;
use QD\commerce\shipmondo\Shipmondo;
use yii\queue\RetryableJobInterface;

class UpdateStatusWebhook extends BaseJob implements RetryableJobInterface
{
  public int $params;

  /**
   * Defines how long a single run of the queue can take
   *
   * @return void
   */
  public function getTtr()
  {
    return 300;
  }

  /**
   * Defines if the job can be retried when it fails
   *
   * @param int $attempt number
   * @param \Exception|\Throwable $error from last execute of the job
   * @return bool
   */
  public function canRetry($attempt, $error)
  {
    // We have retried 5 times, so we throw an exception to keep the job in the queue and to help us debug the issue
    return $attempt < 5;
  }


  public function execute($queue): void
  {
    //? Shipmondo Statuses
    /**
     * open
     * processing
     * packed
     * cancelled
     * on_hold
     * sent
     * picked_up
     * archived
     * ready_for_pickup
     * released
     */

    $this->setProgress($queue, 0.1);
    if (!isset($this->params['data'])) {
      throw new Exception('No data in request');
    }

    //Get JWT token
    $token = $this->params['data'];

    //Payload
    $this->setProgress($queue, 0.3);
    $payload = Shipmondo::getInstance()->getWebhooks()->decodeWebhook($token);

    //If payload data is not in JSON format, try to decode it
    $this->setProgress($queue, 0.5);
    $data = is_string($payload['data']) ? Json::decode($payload['data']) : $payload['data'];

    //Check if it is a connection test
    if (isset($payload['action']) && $payload['action'] == 'connection_test') {
      throw new Exception('Connection test');
    }

    //Request doesn't contain a order id, return as failure
    if (!isset($data['order_id']) || $data['order_id'] == '') {
      throw new Exception('No order ID in request');
    }

    //Request doesn't contain a order status, return as failure
    if (!isset($data['order_status']) || $data['order_status'] == '') {
      throw new Exception('No order status in request');
    }

    //Get order status and reference
    $shipmondoStatus = $data['order_status'];
    $orderReference = $data['order_id'];

    //Get order
    $this->setProgress($queue, 0.7);
    $order = Order::find()->reference($orderReference)->one();

    //If order is not found and status is cancelled, return as success
    if (!$order && $shipmondoStatus == 'cancelled') {
      return;
    }

    //No order found, return as failure
    if (!$order) {
      throw new Exception('Order with reference ' . $orderReference . ' not found.');
    }

    $this->setProgress($queue, 0.9);
    Shipmondo::getInstance()->getStatusService()->changeOrderStatusFromShipmondoHandle($order, $shipmondoStatus);

    $this->setProgress($queue, 1);
    return;
  }

  protected function defaultDescription(): ?string
  {
    return 'Syncing shipment #' . $this->orderId . ' to Shipmondo';
  }
}
