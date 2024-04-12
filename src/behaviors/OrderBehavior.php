<?php

/**
 * Behavior for the order element
 * Adds extra fields to the order element
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\behaviors;

use Craft;
use craft\commerce\elements\Order;
use QD\commerce\shipmondo\plugin\Table;
use yii\base\Behavior;

class OrderBehavior extends Behavior
{
    /**
     * Id of the order on shipmondo
     *
     * @var string|null
     */
    public $shipmondoId;

    /**
     * Id of the servicepoint selected
     *
     * @var string|null
     */
    public $servicePointId = NULL;

    /**
     * Indicate if servicepoint snapshot should be cleared
     *
     * @var boolean
     */
    public $clearSnapshot = false;

    /**
     * Indicate if servicepoint id should be cleared
     *
     * @var boolean
     */
    public $clearServicePointId = false;

    /**
     * Snapshot of the servicepoint when it was selected
     *
     * @var string|null
     */
    public $servicePointSnapshot = NULL;


    /**
     * @var string Table name where extra info is stored
     */
    const EXTRAS_TABLE = Table::ORDERINFO;


    // Public Methods
    // =========================================================================

    /**
     * Defines events that this behaviour should apply to.
     * setOrderInfo is called before the order is saved, to set the attributes that shoudl be saved in the extra table
     *
     * @return array
     */
    public function events(): array
    {
        return [
            Order::EVENT_BEFORE_SAVE => [$this, 'setOrderInfo'],
            Order::EVENT_AFTER_SAVE => [$this, 'saveOrderInfo'],
        ];
    }

    /**
     * Returns the link for the order on the shipmondo page
     *
     * @return void
     */
    public function getShipmondoLink(): string
    {
        return "https://app.shipmondo.com/main/app/#/orders/{$this->owner->shipmondoId}";
    }

    /**
     * Gets data from the request and sets the attributes that should be saved in the extra table
     *
     * @return void
     */
    public function setOrderInfo(): void
    {
        //Get the request that is made
        $request = Craft::$app->getRequest();

        //If request is not a console request and has a param method there can be data that should be saved in the extra table
        if (!$request->getIsConsoleRequest() && \method_exists($request, 'getParam')) {

            // If droppointId is set, store it on the order
            $servicePointId = $request->getParam('servicePointId');
            $servicePointSnapshot = $request->getParam('servicePointSnapshot');
            $shipmondoId = $request->getParam('shipmondoId');
            $bodyParams = $request->getBodyParams();

            // If servicePointId is set, store it on the order to be saved in saveOrderInfo
            if ($servicePointId && $servicePointId !== NULL && $servicePointId != 'null') {
                $this->servicePointId = $servicePointId;
            }

            // If servicePointSnapshot is set, store it on the order to be saved in saveOrderInfo
            if ($servicePointSnapshot && $servicePointSnapshot !== NULL && $servicePointSnapshot != 'null') {
                $this->servicePointSnapshot = $servicePointSnapshot;
            }

            // If shipmondoId is set, store it on the order to be saved in saveOrderInfo
            if ($shipmondoId && $shipmondoId !== NULL && $shipmondoId == 'null') {
                $this->shipmondoId = $shipmondoId;
            }

            // Checks if servicePointSnapshot param is sent, and if it is, checks if it is null or empty string. If it is, it sets the clearSnapshot to true
            if (isset($bodyParams['servicePointSnapshot']) && ($servicePointSnapshot == NULL || $servicePointSnapshot == 'null' || (\is_string($servicePointSnapshot) && \strlen($servicePointSnapshot) == 0))) {
                $this->clearSnapshot = true;
            }

            // Checks if servicePointId param is sent, and if it is, checks if it is null or empty string. If it is, it sets the clearServicePointId to true
            if (isset($bodyParams['servicePointId']) && ($servicePointId == NULL || $servicePointId == 'null' || (\is_string($servicePointId) && \strlen($servicePointId) == 0))) {
                $this->clearServicePointId = true;
            }
        }
    }

    /**
     * Saves extra attributes that the Behavior injects.
     *
     * @return void
     */
    public function saveOrderInfo(): void
    {
        //Initialize empty data array to save it to the database
        $data = [];

        //Shipmondo id is set, save it to the database
        if ($this->shipmondoId) {
            $data['shipmondoId'] = $this->shipmondoId;
        }

        //Servicepoint id is set, save it to the database
        if ($this->servicePointId !== null) {
            $data['servicePointId'] = $this->servicePointId;
        }

        //Servicepoint snapshot is set, save it to the database
        if ($this->servicePointSnapshot !== null) {
            $data['servicePointSnapshot'] = $this->servicePointSnapshot;
        }

        //Clear snapshot flag is set, clear the snapshot from database
        if ($this->clearSnapshot) {
            $data['servicePointSnapshot'] = null;
        }

        //Clear servicepoint id flag is set, clear the servicepoint id from database
        if ($this->clearServicePointId) {
            $data['servicePointId'] = null;
        }

        //We have data to save, save it to the extra table
        if ($data) {
            $data['id'] = $this->owner->id;

            Craft::$app->getDb()->createCommand()
                ->upsert(self::EXTRAS_TABLE, $data)
                ->execute();
        }
    }
}
