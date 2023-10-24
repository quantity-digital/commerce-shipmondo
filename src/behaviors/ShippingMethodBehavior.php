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
    public $templateId = null;
    public $carrierCode = null;
    public $useOwnAgreement = null;
    public $productCode = null;
    public $requireServicePoint = null;
    public $requiredFields = null;

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

    public function getUseOwnAgreement()
    {
        // If not set, get database and populate the object
        if ($this->useOwnAgreement == null) {
            $this->getShipmondoData();
        }

        //Return the value
        return $this->useOwnAgreement;
    }

    public function getShipmondoTemplateId()
    {
        // If not set, get database and populate the object
        if ($this->templateId == null) {
            $this->getShipmondoData();
        }

        //Return the value
        return $this->templateId;
    }

    public function getCarrierCode()
    {
        // If not set, get database and populate the object
        if ($this->carrierCode == null) {
            $this->getShipmondoData();
        }

        //Return the value
        return $this->carrierCode;
    }

    public function getRequiredFields()
    {
        // If not set, get database and populate the object
        if ($this->requiredFields == null) {
            $this->getShipmondoData();
        }

        //Return the value
        return $this->requiredFields;
    }

    public function getProductCode()
    {
        // If not set, get database and populate the object
        if ($this->productCode == null) {
            $this->getShipmondoData();
        }

        //Return the value
        return $this->productCode;
    }

    protected function getShipmondoData()
    {
        // Get the database row for the current shipping method (owner)
        // We query all columns, to minimize further queries to the database for each column
        $row = (new Query())
            ->select('*')
            ->from(self::EXTRAS_TABLE)
            ->where('id = :id', array(':id' => $this->owner->id))
            ->one();

        // If no row is found, return
        if (!$row) {
            return;
        }

        //Set templateId from database record
        $this->templateId = isset($row['templateId']) ? $row['templateId'] : null;

        //Set carrierCode from database record
        $this->carrierCode = isset($row['carrierCode']) ? $row['carrierCode'] : null;

        //Set useOwnAgreement from database record
        $this->useOwnAgreement = isset($row['useOwnAgreement']) ? $row['useOwnAgreement'] : null;

        //Set productCode from database record
        $this->productCode = isset($row['productCode']) ? $row['productCode'] : null;

        //Set requireServicePoint from database record
        $this->requireServicePoint = isset($row['requireServicePoint']) ? $row['requireServicePoint'] : null;

        //Set requiredFields from database record
        $this->requiredFields = isset($row['requiredFields']) ? explode(",", $row['requiredFields']) : null;
    }

    public function saveShippingInfo($event)
    {
        $request = Craft::$app->getRequest();
        $this->templateId = $request->getParam('shipmondoTemplateId');
        $this->useOwnAgreement = $request->getParam('useOwnAgreement');

        // Return if no templateId is set
        if (!$this->templateId) {
            return;
        }

        // Get the template from Shipmondo API and return if not found or product code is not set
        $template = Shipmondo::getInstance()->getShipmondoApi()->getTemplate($this->templateId)->getOutput();
        if (!$template || !isset($template['product_code'])) {
            return;
        }

        // Get the product from Shipmondo API and return if not found
        $product = Shipmondo::getInstance()->getShipmondoApi()->getProduct($template['product_code'])->getOutput();

        if (!isset($product[0])) {
            return;
        }

        // Get the carrier code, name and if servicepoint is required from the product
        $product = $product[0];
        $carrierCode = $product['carrier']['code'];
        $carrierName = $product['carrier']['name'];
        $requireServicePoint = $product['service_point_required'];
        $requiredFields = $product['required_fields'];

        // Save the data to the database using upsert, which will update or inserrt the row
        Craft::$app->getDb()->createCommand()
            ->upsert(self::EXTRAS_TABLE, [
                'id' => $event->sender->id,
                'templateId' => $this->templateId,
                'carrierCode' => $carrierCode,
                'carrierName' => $carrierName,
                'productCode' => $template['product_code'],
                'useOwnAgreement' => $this->useOwnAgreement,
                'requireServicePoint' => $requireServicePoint,
                'requiredFields' => $requiredFields
            ])
            ->execute();
    }
}
