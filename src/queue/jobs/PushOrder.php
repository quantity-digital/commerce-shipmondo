<?php

namespace QD\commerce\shipmondo\queue\jobs;

use craft\commerce\elements\Order;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use Exception;
use QD\commerce\shipmondo\Shipmondo;
use yii\queue\RetryableJobInterface;

class PushOrder extends BaseJob implements RetryableJobInterface
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
        return $attempt < 5;
    }

    public function execute($queue): void
    {
        $this->setProgress($queue, 0.1);
        // Get the order based on the orderId
        $order = Order::find()->id($this->orderId)->status(null)->one();

        $this->setProgress($queue, 0.2);

        // No order found in craft, return and end the job
        if (!$order) {
            return;
        }

        $this->setProgress($queue, 0.3);

        // Push the order to Shipmondo
        $push = Shipmondo::getInstance()->getOrders()->pushOrder($order);

        $this->setProgress($queue, 0.4);

        //Push failed, throw exception to allow retry
        if (!$push) {
            throw new Exception('Could not push order #' . $this->orderId . ' to Shipmondo');
        }

        $this->setProgress($queue, 1);
    }

    protected function defaultDescription(): ?string
    {
        return 'Syncing order #' . $this->orderId . ' to Shipmondo';
    }
}
