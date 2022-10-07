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
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin as Commerce;
use craft\commerce\Plugin;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use DateTime;
use Exception;
use QD\commerce\shipmondo\events\ConvertOrder;
use QD\commerce\shipmondo\events\SetOrderLines;
use QD\commerce\shipmondo\queue\jobs\PushOrder;
use QD\commerce\shipmondo\Shipmondo;

class Orders extends Component
{
	const EVENT_AFTER_CONVERT_ORDER = 'afterConvertOrder';
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
	public function convertOrder(Order $order): array
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
			"bill_to" => $this->setBillTo($order),
			"sender" => $this->setSender($order),
			"payment_details" => $this->setPaymentDetails($order),
			"service_point" => $this->setServicePoint($order),
			"order_lines" => $this->setOrderLines($order)
		];

		$event = new ConvertOrder([
			'order' => $order,
			'shipmondoOrder' => $shipmondoOrder,
		]);
		$this->trigger(self::EVENT_AFTER_CONVERT_ORDER, $event);

		return $event->shipmondoOrder;
	}

	public function addSyncJob($event)
	{
		$order = $event->order;
		$orderHistory = $event->orderHistory;
		$newOrderStatusId = $orderHistory->newStatusId;
		$orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($newOrderStatusId);
		$orderStatusHandle = $orderStatus->handle;

		$settings = Shipmondo::getInstance()->getSettings();
		$statusesToPush = $settings->orderStatusesToPush;

		if (\in_array($orderStatusHandle, $statusesToPush)) {
			$this->syncOrder($order);
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
		if (!$order->isCompleted) {
			return;
		}

		if ($order->shipmondoId) {
			return;
		}

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
		if ($order->shipmondoId) {
			return true;
		}

		$convertedOrder = $this->convertOrder($order);
		$response = Shipmondo::getInstance()->getShipmondoApi()->createSalesOrder($convertedOrder);
		try {
		} catch (Exception $exception) {
			return false;
		}

		Shipmondo::getInstance()->getOrderInfos()->saveShipmondoId($order->id, $response['output']['id']);

		return true;
	}



	protected function setShipTo(Order $order): array
	{
		$address = $order->getShippingAddress();
		$phoneHandle = $this->pluginSettings->addresPhoneHandle;

		if (!$address) {
			$address = $order->getBillingAddress();
		}

		if (!$address) {
			return [];
		}

		$receiver = $this->getNameAndAttention($address);

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

	protected function setBillTo(Order $order): array
	{
		$address = $order->getBillingAddress();
		$phoneHandle = $this->pluginSettings->addresPhoneHandle;

		if (!$address) {
			$address = $order->getShippingAddress();
		}

		if (!$address) {
			return [];
		}

		$receiver = $this->getNameAndAttention($address);

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

	protected function setSender($order): array
	{
		$craftMailSettings = App::mailSettings();
		$fromEmail = Commerce::getInstance()->getSettings()->emailSenderAddress ?: $craftMailSettings->fromEmail;

		$phoneHandle = $this->pluginSettings->addresPhoneHandle;

		$address = Commerce::getInstance()->getStore()
			->getStore()
			->getLocationAddress();

		$receiver = $this->getNameAndAttention($address);

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
			"email" => $fromEmail,
			"vat_id" => ($address->organizationTaxId) ? $address->organizationTaxId : '',
		];
	}

	protected function setPaymentDetails(Order $order): array
	{
		return [
			"amount_including_vat" => $order->total,
			"currency_code" => $order->paymentCurrency,
			"vat_amount" => $order->storedTotalTax ?: $order->storedTotalTaxIncluded,
		];
	}

	protected function setServicePoint(Order $order): array
	{
		if (!$order->servicePointId) {
			return [];
		}

		$servicePointDataString = $order->servicePointSnapshot;
		if ($servicePointDataString) {
			$servicePointData = Json::decode($servicePointDataString);
		}
		if (!$servicePointDataString) {
			$fetchedData = Shipmondo::getInstance()->getServicePoints()->getServicePointById($order->servicePointId, $order);
			if (!isset($fetchedData[0])) {
				return [];
			}
			$servicePointData = $fetchedData[0];
		}

		return [
			"id" => $servicePointData['id'],
			"name" => $servicePointData['name'],
			"address1" => $servicePointData['address'],
			"address2" => $servicePointData['address2'],
			"zipcode" => $servicePointData['zipcode'],
			"city" => $servicePointData['city'],
			"country_code" => $servicePointData['country'],
		];
	}

	protected function setOrderLines(Order $order): array
	{
		$lineItems = $order->getLineItems();
		$orderLines = [];

		$itemBarcodeHandle = $this->pluginSettings->itemBarcodeHandle;
		$itemBinHandle = $this->pluginSettings->itemBinHandle;
		$itemImageUrlHandle = $this->pluginSettings->itemImageUrlHandle;

		$commerceSettings = Commerce::getInstance()->getSettings();
		$weightUnits = $commerceSettings->weightUnits;
		$unitsService = Shipmondo::getInstance()->getUnits();

		foreach ($lineItems as $key => $lineItem) {
			$snapShot = \is_string($lineItem->snapshot) ? Json::decode($lineItem->snapshot) : $lineItem->snapshot;

			$exVatPrice = $lineItem->total - $lineItem->tax;
			$exVatUnitPrice = $exVatPrice / $lineItem->qty;

			$variant = $lineItem->purchasable;
			$product = $variant->product;

			$image = ($itemBarcodeHandle) ? (($variant->$itemImageUrlHandle) ? $variant->$itemImageUrlHandle->one() : (($product->$itemImageUrlHandle) ? $product->$itemImageUrlHandle->one() : null)) : null;
			$barcode = ($itemBarcodeHandle) ? (($variant->$itemBarcodeHandle) ? $variant->$itemBarcodeHandle : (($product->$itemBarcodeHandle) ? $product->$itemBarcodeHandle : null)) : null;
			$bin = ($itemBinHandle) ? (($variant->$itemBinHandle) ? $variant->$itemBinHandle : (($product->$itemBinHandle) ? $product->$itemBinHandle : null)) : null;

			$orderLines[] = [
				"line_type" => "item",
				"item_name" => $snapShot['title'],
				"item_sku" => $snapShot['sku'],
				"quantity" => $lineItem->qty,
				"unit_price_excluding_vat" => $exVatUnitPrice,
				"currency_code" => $order->paymentCurrency,
				"unit_weight" => $unitsService->convertToGram($lineItem->weight, $weightUnits),
				"item_barcode" => $barcode,
				"item_bin" => $bin,
				"image_url" => ($image) ? UrlHelper::siteUrl() . $image->url : ''
			];
		}

		$event = new SetOrderLines([
			'order' => $order,
			'lineItems' => $lineItems,
			'orderLines' => $orderLines,
		]);
		$this->trigger(self::EVENT_AFTER_CONVERT_ORDER, $event);

		return $event->orderLines;
	}

	protected function getNameAndAttention($address)
	{

		if ($address->organization && strlen($address->organization)) {
			return [
				'name' => $address->organization,
				'attention' => $this->getFullName($address)
			];
		}

		return [
			'name' => $this->getFullName($address),
			'attention' => null
		];
	}

	protected function getFullName($address)
	{
		if ($address->fullName && strlen($address->fullName)) {
			exit('test');
			return $address->fullName;
		}

		return $address->firstName . ' ' . $address->lastName;
	}
}
