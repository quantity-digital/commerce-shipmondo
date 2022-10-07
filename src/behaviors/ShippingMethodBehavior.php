<?php

namespace QD\commerce\shipmondo\behaviors;

use Craft;
use craft\commerce\models\ShippingMethod;
use craft\db\Query;
use QD\commerce\shipmondo\plugin\Table;
use QD\commerce\shipmondo\Shipmondo;
use yii\base\Behavior;

class ShippingMethodBehavior extends Behavior
{
	/**
	 * @var string|null
	 */
	public $templateId;

	/**
	 * @var string Table name where extra info is stored
	 */
	const EXTRAS_TABLE = Table::SHIPPINGMETHODS;


	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function events()
	{
		return [
			ShippingMethod::EVENT_DEFINE_EXTRA_FIELDS => [$this, 'saveShippingInfo'],
		];
	}

	public function getShipmondoTemplateId()
	{
		$row = (new Query())
			->select('*')
			->from(self::EXTRAS_TABLE)
			->where('id = :id', array(':id' => $this->owner->id))
			->one();

		return isset($row['templateId']) ? $row['templateId'] : null;
	}

	public function getCarrierCode()
	{
		$row = (new Query())
			->select('*')
			->from(self::EXTRAS_TABLE)
			->where('id = :id', array(':id' => $this->owner->id))
			->one();

		return isset($row['carrierCode']) ? $row['carrierCode'] : null;
	}

	public function saveShippingInfo($event)
	{
		$request = Craft::$app->getRequest();
		$this->templateId = $request->getParam('shipmondoTemplateId');

		if ($this->templateId) {

			$template = Shipmondo::getInstance()->getShipmondoApi()->getTemplate($this->templateId)->getOutput();
			if (isset($template['product_code'])) {
				$product = Shipmondo::getInstance()->getShipmondoApi()->getProduct($template['product_code'])->getOutput();
				$product = $product[0];
				$carrierCode = $product['carrier']['code'];
				$carrierName = $product['carrier']['name'];
				$servicePointRequired = $product['service_point_required'];

				Craft::$app->getDb()->createCommand()
					->upsert(self::EXTRAS_TABLE, [
						'id' => $event->sender->id,
						'templateId' => $this->templateId,
						'carrierCode' => $carrierCode,
						'carrierName' => $carrierName,
						'requireServicePoint' => $servicePointRequired
					])
					->execute();
			}
		}
	}
}
