<?php

namespace QD\commerce\shipmondo\models;

use Craft;
use craft\base\Model;
use craft\commerce\Plugin as Commerce;

class Settings extends Model
{
	// public $hasCpSection = false;
	// public $enableCaching = true;
	// public $displayDebug = false;
	// public $displayErrors = false;
	// public $fulfilledStatus;
	// public $partiallyFulfilledStatus;
	// public $accountName;
	// public $secretToken;
	// public $webhookSecret;
	// public $channelId;

	public string $apiUser = '';
	public string $apiKey = '';
	public string $webhookKey = '';

	public array $orderStatusesToPush  = [];
	public array $orderStatusMapping = [];

	//Fields
	public ?string $orderNoteHandle = null;
	public ?string $itemBarcodeHandle = null;
	public ?string $itemBinHandle = null;
	public ?string $itemImageUrlHandle = null;
	public ?string $addresPhoneHandle = null;

	public function rules(): array
	{
		return [
			[['apiUser', 'apiKey'], 'required'],
		];
	}

	public function init(): void
	{
		parent::init();
	}

	/**
	 * @return array
	 */
	public function getOrderStatuses(): array
	{
		$orderStatuses = Commerce::getInstance()->getOrderStatuses()->getAllOrderStatuses();
		$options = [];
		foreach ($orderStatuses as $orderStatus) {
			$options[] = ['label' => $orderStatus->name, 'value' => $orderStatus->handle];
		}

		return $options;
	}

	/**
	 * @return array
	 */
	public function getShipmondoStatuses(): array
	{
		return [
			'open' => 'Open',
			'processing' => 'Processing',
			'packed' => 'Packed',
			'cancelled' => 'Cancelled',
			'on_hold' => 'On hold',
			'sent' => 'Sent',
			'picked_up' => 'Picked up',
			'archived' => 'Archived',
			'ready_for_pickup' => 'Ready for pickup',
			'released' => 'Released'
		];
		// $options = [];
		// foreach (OrderSyncStatus::STATUSES as $value => $label) {
		// 	$options[] = ['value' => $value, 'label' => sprintf('%d: %s', $value, $label)];
		// }

		// return $options;
	}

	/**
	 * @return array
	 */
	public function getAvailableTextFields(): array
	{
		$options = [
			['label' => null, 'value' => null],
		];
		foreach (Craft::$app->getFields()->getAllFields() as $field) {
			$options[] = ['label' => $field->name, 'value' => $field->handle];
		}

		return $options;
	}

	/**
	 * Check if any order status change mapping is configured
	 * @return bool
	 */
	public function canChangeOrderStatus(): bool
	{
		return !empty($this->orderStatusMapping);
	}
}
