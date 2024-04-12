<?php

/**
 * Service to handle all shipment related actions
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\elements\Address;
use craft\helpers\App;
use craft\helpers\Json;
use QD\commerce\shipmondo\events\PushShipment as EventsPushShipment;
use QD\commerce\shipmondo\queue\jobs\PushShipment;
use QD\commerce\shipmondo\Shipmondo;

class ShipmentService extends Component
{

    const EVENT_AFTER_SHIPMENT_PUSH = 'afterShipmentPush';

    protected $pluginSettings;

    public function __construct()
    {
        $this->pluginSettings = Shipmondo::$plugin->getSettings();
    }

    public function pushShipment($order, $settings)
    {
        $convertedShipment = $this->convertShipment($order, $settings);
        $response = Shipmondo::getInstance()->getShipmondoApi()->createShipment($convertedShipment);

        if (!$response['output']['id']) {
            return false;
        }

        Shipmondo::getInstance()->getOrderInfos()->saveShipmentId($order->id, $response['output']['id']);

        $event = new EventsPushShipment([
            'order' => $order,
            'settings' => $settings,
        ]);
        $this->trigger(self::EVENT_AFTER_SHIPMENT_PUSH, $event);

        return true;
    }

    public function convertShipment($order, $settings)
    {
        // BASE
        $shippingMethod = $order->getShippingMethod();

        $data = [
            'test_mode' => App::devMode(),
            'own_agreement' => true,
            'product_code' => $shippingMethod->getProductCode(),

            'contents' => isset($settings['contents']) ? $settings['contents'] : false,
            'reference' =>  $order->reference,

            'parcels' => $this->setParcels($order),

            'sender' => $this->setSender(),
            'receiver' => $this->setReceiver($order),
        ];

        $phoneHandle = $this->pluginSettings->addresPhoneHandle;
        $address = $order->getShippingAddress();

        // If Phone is defined on order, use SMS
        if ($phoneHandle && $address->$phoneHandle) {
            $data['service_codes'] = 'EMAIL_NT,SMS_NT';
        }
        // If not, use EMAIL
        else {
            $data['service_codes'] = 'EMAIL_NT';
        }

        // DELIVERY
        $servicepoint = $this->setServicePoint($order);
        if (count($servicepoint)) {
            $data['service_point'] = $servicepoint;
        } else {
            $data['automatic_select_service_point'] = true;
        }

        // PRINT - Disabled as the printer fails after 500 labels in the queue
        // $printer = $this->setPrinter($settings);
        // if ($printer && count($printer)) {
        //     $data['print'] = true;
        //     $data['print_at'] = $printer;
        // }

        return $data;
    }

    /**
     * Set the printer to be used for the shipment
     *
     * @param array $settings
     *
     * @return array
     */
    protected function setPrinter(array $settings): array
    {
        $return = [];

        if (isset($settings['host_name'])) {
            $return['host_name'] = $settings['host_name'];
        }
        if (isset($settings['printer_name'])) {
            $return['printer_name'] = $settings['printer_name'];
        }
        if (isset($settings['label_format'])) {
            $return['label_format'] = $settings['label_format'];
        }

        return $return;
    }

    /**
     * Convert shop address to shipmondo sender array
     *
     * @param [type] $order
     *
     * @return array
     */
    protected function setSender(): array
    {
        // Get sender name and email from Craft Commerce settings
        $craftMailSettings = App::mailSettings();
        $senderName = Commerce::getInstance()->getSettings()->emailSenderName ?: $craftMailSettings->fromName;
        $senderEmail = Commerce::getInstance()->getSettings()->emailSenderAddress ?: $craftMailSettings->fromEmail;

        // Get sender phone handle from plugin settings
        $phoneHandle = $this->pluginSettings->addresPhoneHandle;

        // Get sender address from Craft Commerce settings
        $address = Commerce::getInstance()
            ->getAddresses()
            ->getStoreLocationAddress();

        // If no address is set, return empty array
        if (!$address) {
            return [];
        }

        return [
            "name" => App::parseEnv($senderName),
            "attention" => ($address->attention) ? $address->attention : '',
            "address1" => $address->address1,
            "address2" => $address->address2,
            "zipcode" => $address->zipCode,
            "city" => $address->city,
            "country_code" => $address->countryIso,
            "mobile" => ($phoneHandle && $address->$phoneHandle) ? $address->$phoneHandle : '',
            "email" => App::parseEnv($senderEmail),
            "vat_id" => ($address->businessTaxId) ? $address->businessTaxId : '',
        ];
    }

    /**
     * Convert order to shipmondo receiver array
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return array
     */
    protected function setReceiver(Order $order): array
    {
        // Get shipping address from order
        $address = $order->getShippingAddress();

        // Get phone handle from plugin settings
        $phoneHandle = $this->pluginSettings->addresPhoneHandle;

        // If no shipping address is set, use billing address
        if (!$address) {
            $address = $order->getBillingAddress();
        }

        // If no address is set, return empty array
        if (!$address) {
            return [];
        }

        //Get receiver name and attention
        $receiver = $this->getNameAndAttention($address);

        // Return receiver array
        return [
            "name" => $receiver['name'],
            "attention" => $receiver['attention'],
            "address1" => $address->addressLine1,
            "address2" => $address->addressLine2,
            "zipcode" => $address->zipCode,
            "city" => $address->city,
            "country_code" => $address->countryIso,
            "email" => $order->email,
            "mobile" => ($phoneHandle && $address->$phoneHandle) ? $address->$phoneHandle : '',
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
            "address2" => $servicePointData['address2'],
            "zipcode" => $servicePointData['zipcode'],
            "city" => $servicePointData['city'],
            "country_code" => $servicePointData['country'],
        ];
    }

    /**
     * Convert order to shipmondo parcel array
     *
     * @param \craft\commerce\elements\Order $order
     *
     * @return array
     */
    protected function setParcels(Order $order): array
    {
        // Get line items from order
        $lineItems = $order->getLineItems();

        //Get weight units from commerce settings, as shipmondo only accepts grams
        $commerceSettings = Commerce::getInstance()->getSettings();
        $weightUnits = $commerceSettings->weightUnits;
        $unitsService = Shipmondo::getInstance()->getUnits();

        // GET COMBINED WEIGHT OF ITEMS
        $weight = 0;
        foreach ($lineItems as $lineItem) {
            $weight = $weight + $lineItem->weight;
        }

        // Set parcel to the weight, converted to grams
        $parcel = [
            "weight" => $unitsService->convertToGram($weight, $weightUnits) ?: 1000,
        ];

        // Return parcel array. Is wrapped in array, as shipmondo expects an array of parcels
        return [$parcel];
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
        if ($address->businessName && strlen($address->businessName)) {
            return [
                'name' => $address->businessName,
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
