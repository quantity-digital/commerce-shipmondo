<?php

namespace QD\commerce\shipmondo\behaviors;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use QD\commerce\shipmondo\plugin\Table;
use yii\base\Behavior;

class OrderQueryBehavior extends Behavior
{
	/**
	 * @var mixed Value
	 */
	public $shipmondoId;

	/**
	 * @var mixed Value
	 */
	public $servicePointId;

	public $servicePointSnapshot;

	/**
	 * @inheritdoc
	 */
	public function events()
	{
		return [
			ElementQuery::EVENT_BEFORE_PREPARE => 'beforePrepare',
		];
	}

	/**
	 * Applies the shipmondoId param to the query.
	 *
	 * @param mixed $value
	 */
	public function shipmondoId($value)
	{
		$this->shipmondoId = $value;
	}

	/**
	 * Applies the servicePointId param to the query..
	 *
	 * @param mixed $value
	 */
	public function servicePointId($value)
	{
		$this->servicePointId = $value;
	}

	/**
	 * Prepares the user query.
	 */
	public function beforePrepare()
	{
		if ($this->owner->select === ['COUNT(*)']) {
			return;
		}

		// Join our `orderextras` table:
		$this->owner->query->leftJoin(Table::ORDERINFO_STRING . ' shipmondo', '`shipmondo`.id = `commerce_orders`.`id`');

		// Select custom columns:
		$this->owner->query->addSelect([
			'shipmondo.shipmondoId',
			'shipmondo.servicePointId',
			'shipmondo.servicePointSnapshot',
		]);

		if (!is_null($this->shipmondoId)) {
			$this->owner->subQuery->andWhere(Db::parseParam('shipmondo.shipmondoId', $this->shipmondoId));
		}

		if (!is_null($this->servicePointId)) {
			$this->owner->subQuery->andWhere(Db::parseParam('shipmondo.servicePointId', $this->servicePointId));
		}
	}
}
