<?php

namespace QD\commerce\shipmondo\queue\jobs;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use Exception;
use QD\commerce\shipmondo\Shipmondo;
use yii\queue\RetryableJobInterface;

class CancelOrder extends BaseJob implements RetryableJobInterface
{
    public int $orderId;

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
        $this->setProgress($queue, 0.1);

        // Get the order based on the orderId
        $order = Order::find()->id($this->orderId)->status(null)->one();

        $cancelledStatus = Shipmondo::getInstance()->getStatusService()->getOrderStatusByShipmondoHandle('cancelled');

        // If the order is not in Shipmondo or the order is not cancelled in craft, we return
        if (!$cancelledStatus || !$order->shipmondoId || $cancelledStatus->id != $order->orderStatusId) {
            return;
        }

        // Cancel the order in Shipmondo
        Shipmondo::getInstance()->getShipmondoApi()->cancelSalesOrder($order->shipmondoId);

        // Set the progress to 100%
        $this->setProgress($queue, 1);
    }

    protected function defaultDescription(): ?string
    {
        return 'Cancel order #' . $this->orderId . ' in Shipmondo';
    }
}
