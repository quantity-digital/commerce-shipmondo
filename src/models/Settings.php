<?php

namespace QD\commerce\shipmondo\models;

use Craft;
use craft\base\Model;
use craft\commerce\Plugin as Commerce;
use QD\commerce\shipmondo\plugin\Data;

class Settings extends Model
{
    public string $apiUser = '';
    public string $apiKey = '';
    public string $webhookKey = '';

    public array $orderStatusesToPush  = [];
    public array $orderStatusesToUpdate  = [];
    public array $orderStatusMapping = [];

    //Fields
    public ?string $orderNoteHandle = null;
    public ?string $itemBarcodeHandle = null;
    public ?string $itemBinHandle = null;
    public ?string $itemImageUrlHandle = null;
    public ?string $itemProductImageUrlHandle = null;
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
            Data::STATUS_OPEN => 'Open',
            Data::STATUS_PROCESSING => 'Processing',
            Data::STATUS_PACKED => 'Packed',
            Data::STATUS_CANCELLED => 'Cancelled',
            Data::STATUS_ON_HOLD => 'On hold',
            Data::STATUS_SENT => 'Sent',
            Data::STATUS_PICKED_UP => 'Picked up',
            Data::STATUS_ARCHIVED => 'Archived',
            Data::STATUS_READY_FOR_PICKUP => 'Ready for pickup',
            Data::STATUS_RELEASED => 'Released'
        ];
    }

    /**
     * @return array
     */
    public function getAvailableTextFields(): array
    {
        // DEFAULT
        $options = [
            ['label' => null, 'value' => null],
        ];

        // SKU
        $options[] = ['label' => 'Sku', 'value' => 'sku'];

        // GET FIELDS
        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            $options[] = ['label' => $field->name, 'value' => $field->handle];
        }

        // RETURN
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
