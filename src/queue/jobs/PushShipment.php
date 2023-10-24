<?php

namespace QD\commerce\shipmondo\queue\jobs;

use craft\commerce\elements\Order;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use Exception;
use QD\commerce\shipmondo\Shipmondo;
use yii\queue\RetryableJobInterface;

class PushShipment extends BaseJob implements RetryableJobInterface
{
    public int $orderId;
    public $settings;

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

        //Find order
        $order = Order::find()->id($this->orderId)->status(null)->one();

        $this->setProgress($queue, 0.2);

        //No order exists, so we return and end queue job
        if (!$order) {
            return;
        }

        $this->setProgress($queue, 0.3);

        //Push shipment to Shipmondo
        $push = Shipmondo::getInstance()->getShipment()->pushShipment($order, $this->settings);

        $this->setProgress($queue, 0.4);

        //Push failed, so we throw exception to trigger retry after 300 seconds
        if (!$push) {
            throw new Exception('Could not push shipment #' . $this->orderId . ' to Shipmondo');
        }

        $this->setProgress($queue, 1);
    }

    protected function defaultDescription(): ?string
    {
        return 'Syncing shipment #' . $this->orderId . ' to Shipmondo';
    }
}
