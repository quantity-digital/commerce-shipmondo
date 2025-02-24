<?php

namespace QD\commerce\shipmondo\queue\jobs;

use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\queue\BaseJob;
use Exception;
use QD\commerce\shipmondo\Shipmondo;
use yii\queue\RetryableJobInterface;

class UpdateOrder extends BaseJob implements RetryableJobInterface
{
    public int $orderId;

    /**
     * Defines how long a single run of the queue can take
     *
     * @return void
     */
    public function getTtr()
    {
        return 60;
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
        $this->setProgress($queue, 0.1);

        //Find order
        $order = Order::find()->id($this->orderId)->status(null)->one();

        //No order exists, so we return and end queue job
        if (!$order) {
            return;
        }

        //Get processing status
        $processingStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusByHandle('processing', $order->storeId);

        //No shipmondoId or order is not in processing status, so we return and end queue job
        if (!$order->shipmondoId || $processingStatus->id != $order->orderStatusId) {
            return;
        }

        //Get existing salesorder, to get the latest data to be used for when converting the order
        $salesOrder =  Shipmondo::getInstance()->getShipmondoApi()->getSalesOrder($order->shipmondoId);

        //Convert order
        $convertedOrder = Shipmondo::getInstance()->getOrders()->convertOrder($order, $salesOrder);
        $response =  Shipmondo::getInstance()->getShipmondoApi()->updateSalesOrder($order->shipmondoId, $convertedOrder);

        //If response is false, we throw an exception to retry the job
        if (!$response) {
            throw new Exception('Could not update order #' . $this->orderId . ' in Shipmondo');
        }

        $this->setProgress($queue, 1);
    }

    protected function defaultDescription(): ?string
    {
        return 'Update order #' . $this->orderId . ' in Shipmondo';
    }
}
