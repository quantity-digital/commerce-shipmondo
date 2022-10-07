<?php


namespace QD\commerce\shipmondo\records;

use craft\commerce\elements\Order;
use craft\db\ActiveRecord;
use QD\commerce\shipmondo\plugin\Table;
use yii\db\ActiveQuery;

class OrderInfo extends ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return Table::ORDERINFO;
	}

	/**
	 * @return ActiveQuery
	 */
	public function getOrder(): ActiveQuery
	{
		return $this->hasOne(Order::class, ['id' => 'id']);
	}
}
