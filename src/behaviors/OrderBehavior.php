<?php

namespace QD\commerce\shipmondo\behaviors;

use Craft;
use craft\commerce\elements\Order;
use QD\commerce\shipmondo\plugin\Table;
use yii\base\Behavior;

class OrderBehavior extends Behavior
{
	/**
	 * @var string|null
	 */
	public $shipmondoId;

	/**
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
	 * Snapshot of the servicepoint when it was selected
	 *
	 * @var string|null
	 */
	public string|null $servicePointSnapshot = NULL;


	/**
	 * @var string Table name where extra info is stored
	 */
	const EXTRAS_TABLE = Table::ORDERINFO;


	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
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

	public function setOrderInfo(): void
	{

		$request = Craft::$app->getRequest();

		if (!$request->getIsConsoleRequest() && \method_exists($request, 'getParam')) {

			// If droppointId is set, store it on the order
			$servicePointId = $request->getParam('servicePointId');
			$servicePointSnapshot = $request->getParam('servicePointSnapshot');
			$shipmondoId = $request->getParam('shipmondoId');
			$bodyParams = $request->getBodyParams();

			if ($servicePointId && $servicePointId !== NULL && $servicePointId != 'null') {
				$this->servicePointId = $servicePointId;
			}

			if ($servicePointSnapshot && $servicePointSnapshot !== NULL && $servicePointSnapshot != 'null') {
				$this->servicePointSnapshot = $servicePointSnapshot;
			}

			if ($shipmondoId && $shipmondoId !== NULL && $shipmondoId == 'null') {
				$this->shipmondoId = $shipmondoId;
			}

			if (isset($bodyParams['servicePointSnapshot']) && ($servicePointSnapshot == NULL || $servicePointSnapshot == 'null' || (\is_string($servicePointSnapshot) && \strlen($servicePointSnapshot) == 0))) {
				$this->clearSnapshot = true;
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
		$data = [];
		if ($this->shipmondoId) {
			$data['shipmondoId'] = $this->shipmondoId;
		}

		if ($this->servicePointId !== null) {
			$data['servicePointId'] = $this->servicePointId;
		}

		if ($this->servicePointSnapshot !== null) {
			$data['servicePointSnapshot'] = $this->servicePointSnapshot;
		}

		if ($this->clearSnapshot) {
			$data['servicePointSnapshot'] = null;
		}

		if ($data) {
			$data['id'] = $this->owner->id;

			Craft::$app->getDb()->createCommand()
				->upsert(self::EXTRAS_TABLE, $data)
				->execute();
		}
	}
}
