<?php

/**
 * Service to handle all order related actions
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\Plugin as Commerce;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\events\ModelEvent;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\Queue;
use DateTime;
use Exception;
use QD\commerce\shipmondo\events\ConvertOrder;
use QD\commerce\shipmondo\events\SetOrderLines;
use QD\commerce\shipmondo\queue\jobs\CancelOrder;
use QD\commerce\shipmondo\queue\jobs\PushOrder;
use QD\commerce\shipmondo\queue\jobs\UpdateOrder;
use QD\commerce\shipmondo\Shipmondo;
use verbb\giftvoucher\elements\Voucher;

class Orders extends Component
{
    const EVENT_AFTER_CONVERT_ORDER = 'afterConvertOrder';
    const EVENT_AFTER_CREATE_LINES = 'afterCreateOrderLines';
    const EVENT_BEFORE_CONVERT_ORDER = 'beforeConvertOrder';

    protected $pluginSettings;

    public function __construct()
    {
        $this->pluginSettings = Shipmondo::$plugin->getSettings();
    }

    /**
     * Converts Commerce order to Shipmondo order array
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return array
     */
    public function convertOrder(Order $order, array $salesOrder = []): array
    {
        $orderNoteHandle = $this->pluginSettings->orderNoteHandle;
        $shippingMethod = $order->getShippingMethod();

        $shipmondoOrder = [
            "order_id" => $order->reference,
            "ordered_at" => $order->dateOrdered->format(DateTime::ATOM),
            "source_name" => $order->orderSite->name,
            "order_note" => ($orderNoteHandle) ? $order->$orderNoteHandle : '',
            "archived" => false,
            "shipment_template_id" => $shippingMethod->getShipmondoTemplateId(),
            "enable_customs" => false,
            "use_item_weight" => true,

            "ship_to" => $this->setShipTo($order),
            // "bill_to" => $this->setBillTo($order),
            "sender" => $this->setSender($order),
            "payment_details" => $this->setPaymentDetails($order),
            "service_point" => $this->setServicePoint($order),
            "order_lines" => $this->setOrderLines($order, isset($salesOrder['order_lines']) ? $salesOrder['order_lines'] : [])
        ];

        $event = new ConvertOrder([
            'order' => $order,
            'shipmondoOrder' => $shipmondoOrder,
        ]);
        $this->trigger(self::EVENT_AFTER_CONVERT_ORDER, $event);

        return $event->shipmondoOrder;
    }

    /**
     * Handle order save event
     *
     * @param \craft\events\ModelEvent $event
     *
     * @return void
     */
    public function handleOrderSave(ModelEvent $event): void
    {
        //Order is new, so we dont need to do anything
        if ($event->isNew) {
            return;
        }

        //Get the order from the event
        $order = $event->sender;

        //Order is not completed, so we dont need to do anything
        if (!$order->isCompleted) {
            return;
        }

        //Get settings from shipmondo plugin
        $settings = $this->pluginSettings;

        //Get list of order statuses that should trigger a sync
        $statusesToUpdate = $settings->orderStatusesToUpdate;

        //Get the order status handle of the current order
        $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($order->orderStatusId);
        $orderStatusHandle = $orderStatus->handle;

        //Order has status that should be updated in Shipmondo
        if (\in_array($orderStatusHandle, $statusesToUpdate)) {
            $this->updateSalesOrder($order);
        }
    }

    /**
     * Handle order status change
     *
     * @param \craft\commerce\events\OrderStatusEvent $event
     *
     * @return void
     */
    public function handleStatusChange(OrderStatusEvent $event): void
    {
        //Get the order from the event
        $order = $event->order;

        //Get the orderhistory from the event
        $orderHistory = $event->orderHistory;

        //Get the new order status id
        $newOrderStatusId = $orderHistory->newStatusId;

        //Get the new order status from the id
        $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($newOrderStatusId);

        //No order status found, so we dont need to do anything
        if (!$orderStatus) {
            return;
        }

        //Get the order status handle
        $orderStatusHandle = $orderStatus->handle;

        //Get settings from shipmondo plugin
        $settings = $this->pluginSettings;

        //Get statuses that should be pushed to Shipmondo
        $statusesToPush = $settings->orderStatusesToPush;

        //Order has status that should be pushed to Shipmondo. Return after, as no other actions should be taken.
        if (\in_array($orderStatusHandle, $statusesToPush)) {
            $this->syncOrder($order);
            return;
        }

        //Order has status that should be cancelled in Shipmondo. Return after, as no other actions should be taken.
        $cancelledStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusByHandle('cancelled');
        if ($cancelledStatus && $cancelledStatus->id == $newOrderStatusId) {
            $this->cancelOrder($order);
            return;
        }
    }

    /**
     * Add sync job
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return void
     */
    public function syncOrder(Order $order): void
    {
        //Order is not completed, so we dont want to sync it
        if (!$order->isCompleted) {
            return;
        }

        //Order is already synced to Shipmondo
        if ($order->shipmondoId) {
            return;
        }

        //Add sync job to queue
        Queue::push(new PushOrder([
            'orderId' => $order->id,
        ]));
    }

    /**
     * Push an order to Shipmondo
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return boolean
     */
    public function pushOrder(Order $order): bool
    {
        //Order is already synced to Shipmondo
        if ($order->shipmondoId) {
            return true;
        }

        //Convert order to Shipmondo order array
        $convertedOrder = $this->convertOrder($order);

        //Try to push order to Shipmondo
        try {
            $response = Shipmondo::getInstance()->getShipmondoApi()->createSalesOrder($convertedOrder);
        } catch (Exception $exception) {
            //Log error, and return false to indicate failure
            Craft::error($exception->getMessage(), 'shipmondo');
            return false;
        }

        //Save Shipmondo order ID to order
        Shipmondo::getInstance()->getOrderInfos()->saveShipmondoId($order->id, $response['output']['id']);

        return true;
    }

    /**
     * Adds queujob to update order in Shipmondo
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return void
     */
    public function updateSalesOrder(Order $order)
    {
        //Order is not completed, so we cant update it in shipmondo as it doesnt exist there
        if (!$order->isCompleted) {
            return;
        }

        //Order is not synced to Shipmondo, so we cant update it
        if (!$order->shipmondoId) {
            return;
        }

        //Add update job to queue
        Queue::push(new UpdateOrder([
            'orderId' => $order->id,
        ]), 10, 10);
    }


    /**
     * Adds queujob to cancel order in Shipmondo
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return void
     */
    public function cancelOrder(Order $order): void
    {
        //Order is not completed, so we can't cancel it in shipmondo as it doesnt exist there
        if (!$order->isCompleted) {
            return;
        }

        //Order is not synced to Shipmondo, so we can't cancel it as it doesnt exist there
        if (!$order->shipmondoId) {
            return;
        }

        //Add cancel job to queue with 30 seconds delay, as we want to give the user time to change their mind
        Queue::push(new CancelOrder([
            'orderId' => $order->id,
        ]), 10, 30);
    }

    /**
     * Convert order shipping address to Shipmondo address array
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return array
     */
    protected function setShipTo(Order $order): array
    {
        //Get address from order
        $address = $order->getShippingAddress();

        //Get phone handle from settings
        $phoneHandle = $this->pluginSettings->addresPhoneHandle;

        //If no shipping address is set, use billing address
        if (!$address) {
            $address = $order->getBillingAddress();
        }

        //If no billing address is set, return empty array
        if (!$address) {
            return [];
        }

        //Get name and attention from address to be used in the address array
        $receiver = $this->getNameAndAttention($address);

        //Return address array
        return [
            "name" => $receiver['name'],
            "attention" => $receiver['attention'],
            "address1" => $address->addressLine1,
            "address2" => $address->addressLine2,
            "zipcode" => $address->postalCode,
            "city" => $address->locality,
            "country_code" => $address->countryCode,
            "email" => $order->email,
            "mobile" => ($phoneHandle && $address->$phoneHandle) ? $address->$phoneHandle : '',
        ];
    }

    /**
     * Convert order billing address to Shipmondo address array
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return array
     */
    protected function setBillTo(Order $order): array
    {
        //Get address from order
        $address = $order->getBillingAddress();

        //Get phone handle from settings
        $phoneHandle = $this->pluginSettings->addresPhoneHandle;

        //If no billing address is set, use shipping address
        if (!$address) {
            $address = $order->getShippingAddress();
        }

        //If no shipping address is set, return empty array
        if (!$address) {
            return [];
        }

        //Get name and attention from address to be used in the address array
        $receiver = $this->getNameAndAttention($address);

        //Return address array
        return [
            "name" => $receiver['name'],
            "attention" => $receiver['attention'],
            "address1" => $address->addressLine1,
            "address2" => $address->addressLine2,
            "zipcode" => $address->postalCode,
            "city" => $address->locality,
            "country_code" => $address->countryCode,
            "email" => $order->email,
            "mobile" => ($phoneHandle && $address->$phoneHandle) ? $address->$phoneHandle : '',
        ];
    }

    /**
     * Convert sender address to Shipmondo sender array
     *
     * @param [type] $order
     *
     * @return array
     */
    protected function setSender($order): array
    {
        //Get sender name and email from settings
        $craftMailSettings = App::mailSettings();
        $senderName = Commerce::getInstance()->getSettings()->emailSenderName ?: $craftMailSettings->fromName;
        $senderEmail = Commerce::getInstance()->getSettings()->emailSenderAddress ?: $craftMailSettings->fromEmail;

        //Get phone handle from settings
        $phoneHandle = $this->pluginSettings->addresPhoneHandle;

        //Get store address
        $address = Commerce::getInstance()->getStore()
            ->getStore()
            ->getLocationAddress();

        //If no store address is set, return empty array
        if (!$address) {
            return [];
        }

        //Return sender array
        return [
            "name" => App::parseEnv($senderName),
            "attention" => ($address->attention) ? $address->attention : '',
            "address1" => $address->addressLine1,
            "address2" => $address->addressLine2,
            "zipcode" => $address->postalCode,
            "city" => $address->locality,
            "country_code" => $address->countryCode,
            "mobile" => ($phoneHandle && $address->$phoneHandle) ? $address->$phoneHandle : '',
            "email" => App::parseEnv($senderEmail),
            "vat_id" => ($address->organizationTaxId) ? $address->organizationTaxId : '',
        ];
    }

    /**
     * Convert payment details to Shipmondo payment details array
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return array
     */
    protected function setPaymentDetails(Order $order): array
    {
        return [
            "amount_including_vat" => $order->total,
            "currency_code" => $order->paymentCurrency,
            "vat_amount" => $order->storedTotalTax ?: $order->storedTotalTaxIncluded,
        ];
    }

    /**
     * Apply service point to shipmondo order array
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return array
     */
    protected function setServicePoint(Order $order): array
    {
        //If no service point is set, return empty array
        if (!$order->servicePointId) {
            return [];
        }

        //Get service point data from order snapshot
        $servicePointDataString = $order->servicePointSnapshot;

        //If we have snapshot, decode the json string
        if ($servicePointDataString) {
            $servicePointData = Json::decode($servicePointDataString);
        }

        //If we don't have snapshot, fetch the data from shipmondo
        if (!$servicePointDataString) {
            //Fetch service point data from shipmondo api by using the service point id from the order
            $fetchedData = Shipmondo::getInstance()->getServicePoints()->getServicePointById($order->servicePointId, $order);

            //If no data is returned, return empty array
            if (!isset($fetchedData[0])) {
                return [];
            }

            //Set service point data to match the returned data from the api
            $servicePointData = $fetchedData[0];
        }

        //Return service point array
        return [
            "id" => $servicePointData['id'],
            "name" => $servicePointData['name'],
            "address1" => $servicePointData['address'],
            "address2" => isset($servicePointData['address2']) ? $servicePointData['address2'] : null,
            "zipcode" => $servicePointData['zipcode'],
            "city" => $servicePointData['city'],
            "country_code" => $servicePointData['country'],
        ];
    }

    /**
     * Convert order lines to Shipmondo order lines array
     *
     * @param \craft\commerce\elements\Order $order
     * @param array|null $saleOrderLines
     *
     * @return array
     */
    protected function setOrderLines(Order $order, array $saleOrderLines = null): array
    {
        //Get line items from order
        $lineItems = $order->getLineItems();
        $orderLines = [];

        //Get item barcode handle from settings
        $itemBarcodeHandle = $this->pluginSettings->itemBarcodeHandle;

        //Get item bin handle from settings
        $itemBinHandle = $this->pluginSettings->itemBinHandle;

        //Get item image url handle from settings
        $itemImageUrlHandle = $this->pluginSettings->itemImageUrlHandle;

        //Get item product image url handle from settings
        $itemProductImageUrlHandle = $this->pluginSettings->itemProductImageUrlHandle;

        //Get commerce settings
        $commerceSettings = Commerce::getInstance()->getSettings();

        //Get weight units used in the shop. Shipmondo uses grams, so we might need to convert the weight to grams
        $weightUnits = $commerceSettings->weightUnits;
        $unitsService = Shipmondo::getInstance()->getUnits();

        //Allow other plugins to modify the order lines instead of the plugin
        $beforeEvent = new SetOrderLines([
            'order' => $order,
            'lineItems' => $lineItems,
            'orderLines' => [],
        ]);
        $this->trigger(self::EVENT_BEFORE_CONVERT_ORDER, $beforeEvent);
        $orderLines = $beforeEvent->orderLines;

        //If no order lines are set, convert the line items to order lines
        if (!count($orderLines)) {
            foreach ($lineItems as $key => $lineItem) {

                //Get line item snapshot
                $snapShot = \is_string($lineItem->snapshot) ? Json::decode($lineItem->snapshot) : $lineItem->snapshot;

                //Get ex vat price and ex vat unit price
                $exVatPrice = $lineItem->total - $lineItem->tax;
                $exVatUnitPrice = $exVatPrice / $lineItem->qty;

                //Get variant and product
                $variant = $lineItem->purchasable;
                $product = $variant->product;

                //Get image, barcode and bin fields, if they are set
                $image = ($itemImageUrlHandle) ? (($variant->$itemImageUrlHandle) ? $variant->$itemImageUrlHandle->one() : (($product->$itemImageUrlHandle) ? $product->$itemImageUrlHandle->one() : ($itemProductImageUrlHandle ? ($product->$itemProductImageUrlHandle ? $product->$itemProductImageUrlHandle->one() : null) : null))) : null;
                $barcode = ($itemBarcodeHandle) ? (($variant->$itemBarcodeHandle) ? $variant->$itemBarcodeHandle : (($product->$itemBarcodeHandle) ? $product->$itemBarcodeHandle : null)) : null;
                $bin = ($itemBinHandle) ? (($variant->$itemBinHandle) ? $variant->$itemBinHandle : (($product->$itemBinHandle) ? $product->$itemBinHandle : null)) : null;

                //Set order line data
                $data = [
                    "line_type" => "item",
                    "item_name" => $snapShot['description'],
                    "item_sku" => $snapShot['sku'],
                    "quantity" => $lineItem->qty,
                    "unit_price_excluding_vat" => $exVatUnitPrice,
                    "currency_code" => $order->paymentCurrency,
                    "unit_weight" => $unitsService->convertToGram($lineItem->weight, $weightUnits),
                    "item_barcode" => $barcode,
                    "item_bin" => $bin,
                    "image_url" => ($image) ? $image->url : ''
                ];

                //If we have saleorder lines, this means it's a update to saleorder and we need to set the id of the order line, else Shipmondo will create a new order line
                if ($saleOrderLines) {
                    $index = array_search($snapShot['sku'], array_column($saleOrderLines, "item_sku"));

                    if ($index !== false) {
                        $data['id'] = $saleOrderLines[$index]['id'];
                    }
                }

                $orderLines[] = $data;
            }
        }

        //Allow other modules/plugins to modify orderLines
        $event = new SetOrderLines([
            'order' => $order,
            'lineItems' => $lineItems,
            'orderLines' => $orderLines,
        ]);
        $this->trigger(self::EVENT_AFTER_CREATE_LINES, $event);

        return $event->orderLines;
    }

    /**
     * Get name and attetion based on address
     * If we have company name, Shipmondo requires a attention name
     *
     * @param \craft\elements\Address $address
     *
     * @return array
     */
    protected function getNameAndAttention(Address $address): array
    {
        //If we have a company name, use that as name and fullname as attention
        if ($address->organization && strlen($address->organization)) {
            return [
                'name' => $address->organization,
                'attention' => $this->getFullName($address)
            ];
        }

        //Else return fullname as name and null as attention
        return [
            'name' => $this->getFullName($address),
            'attention' => null
        ];
    }

    /**
     * Get fullname based on address
     *
     * @param \craft\elements\Address $address
     *
     * @return void
     */
    protected function getFullName(Address $address)
    {
        //If we have a fullname, use that
        if ($address->fullName && strlen($address->fullName)) {
            return $address->fullName;
        }

        //Else return firstname and lastname
        return $address->firstName . ' ' . $address->lastName;
    }
}
