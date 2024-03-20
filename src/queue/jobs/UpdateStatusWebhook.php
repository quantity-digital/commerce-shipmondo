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
  public mixed $params;

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

    // Set data
    $this->setProgress($queue, 0.5);
    $action = $payload->action ?? '';
    $data = is_array($payload->data) ? (object) $payload->data : $payload->data ?? '';
    $reference = $data->order_id ?? '';
    $status = $data->order_status ?? '';

    //Check if it is a connection test
    if ($action == 'connection_test') {
      return;
    }

    //Request doesn't contain a order id, return as failure
    if (!$reference) {
      throw new Exception('No order ID in request');
    }

    //Request doesn't contain a order status, return as failure
    if (!$status) {
      throw new Exception('No order status in request');
    }

    //Get order
    $this->setProgress($queue, 0.7);
    $order = Order::find()->reference($reference)->one();

    //If order is not found and status is cancelled, return as success
    if (!$order && $status == 'cancelled') {
      return;
    }

    //No order found, return as failure
    if (!$order) {
      throw new Exception('Order with reference ' . $reference . ' not found.');
    }

    // Update order status
    $this->setProgress($queue, 0.9);
    Shipmondo::getInstance()->getStatusService()->changeOrderStatusFromShipmondoHandle($order, $status);

    // Finish queue job
    return;
  }

  protected function defaultDescription(): ?string
  {
    return 'Shipmondo Webhook: Update order status';
  }
}
