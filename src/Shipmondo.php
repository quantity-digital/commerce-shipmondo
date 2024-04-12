<?php

/**
 * Main class for the Shipmondo plugin
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo;

use Craft;
use yii\log\FileTarget;

use craft\base\Model as BaseModel;
use craft\base\Plugin as BasePlugin;
use craft\commerce\base\ShippingMethod;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\commerce\services\OrderHistories;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\UserPermissions;
use QD\commerce\shipmondo\behaviors\OrderBehavior;
use QD\commerce\shipmondo\behaviors\OrderQueryBehavior;
use QD\commerce\shipmondo\behaviors\ShippingMethodBehavior;
use QD\commerce\shipmondo\models\Settings;
use QD\commerce\shipmondo\plugin\Routes;
use QD\commerce\shipmondo\plugin\Services;
use yii\base\Event;

class Shipmondo extends BasePlugin
{

    use Routes;
    use Services;

    // Static Properties
    // =========================================================================

    public static $plugin;

    /**
     * @var bool
     */
    public static $commerceInstalled = false;

    // Public Properties
    // =========================================================================

    /**
     * Schema version
     *
     * @var string
     */
    public $schemaVersion = "1.0.2";

    /**
     * @inheritDoc
     */
    public $hasCpSettings = true;

    // Public Methods
    // =========================================================================

    // public static function log($message)
    // {
    //     Craft::getLogger()->log($message, \yii\log\Logger::LEVEL_INFO, 'commerce-shipmondo');
    // }

    /**
     * @inheritdoc
     */
    public function init()
    {
        // $fileTarget = new FileTarget([
        //     'logFile' => __DIR__ . '/webhooks.log', // <--- path of the log file
        //     'categories' => ['commerce-shipmondo'] // <--- categories in the file
        // ]);
        // // include the new target file target to the dispatcher
        // Craft::getLogger()->dispatcher->targets[] = $fileTarget;
        parent::init();

        self::$plugin = $this;

        self::$commerceInstalled = class_exists(Commerce::class);

        $this->initComponents();
        $this->_registerCpRoutes();
        $this->_registerSiteRoutes();
        $this->_registerGlobalEvents();
        $this->_registerCpEvents();
        $this->_defineBehaviors();
    }

    public function getPluginName()
    {
        return 'Shipmondo';
    }

    public function getSettingsResponse()
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('shipmondo/settings'));
    }

    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): ?BaseModel
    {
        return new Settings();
    }

    protected function _registerGlobalEvents()
    {
        Event::on(OrderHistories::class, OrderHistories::EVENT_ORDER_STATUS_CHANGE, [$this->getOrders(), 'handleStatusChange']);

        Event::on(Order::class, Order::EVENT_AFTER_SAVE, [$this->getOrders(), 'handleOrderSave']);
    }

    protected function _registerCpEvents()
    {
        //Add shipmondo settings to shippingmethod
        Craft::$app->view->hook('cp.commerce.shippingMethods.edit.content', function (&$context) {
            $shippingMethod = $context['shippingMethod'];

            $shipmondo = $this->getShipmondoApi();
            $context['shipmondoTemplateId'] = $shippingMethod->getShipmondoTemplateId();
            $shipmondoTemplates = $shipmondo->getShipmentTemplates()->getOutput();

            $templates = [
                null => '---'
            ];

            foreach ($shipmondoTemplates as $template) {
                $templates[$template['id']] = $template['name'];
            }

            $context['shipmondoTemplates'] = $templates;

            $context['useOwnAgreement'] = $shippingMethod->getUseOwnAgreement();

            return Craft::$app->view->renderTemplate('commerce-shipmondo/shippingmethod/edit', $context);
        });

        //Add shipmondo details on orderpage
        Craft::$app->getView()->hook('cp.commerce.order.edit.details', function (array &$context) {
            /** @var Order $order */
            $order = $context['order'];
            // $status = $this->orderSync->getOrderSyncStatusByOrderId($order->getId());
            $status = false;

            return Craft::$app->getView()->renderTemplate('commerce-shipmondo/order/order-details-panel', [
                'plugin' => $this,
                'order' => $order,
                'status' => $status,
            ]);
        });
    }

    protected function _defineBehaviors(): void
    {
        /**
         * Order element behaviours
         */
        Event::on(
            Order::class,
            Order::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $e) {
                $e->behaviors['commerce-shipmondo.attributes'] = OrderBehavior::class;
            }
        );

        Event::on(
            OrderQuery::class,
            OrderQuery::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $e) {
                $e->behaviors['commerce-shipmondo.queryparams'] = OrderQueryBehavior::class;
            }
        );

        /**
         * Shippingmethod element behavoiours
         */
        Event::on(
            ShippingMethod::class,
            ShippingMethod::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $e) {
                $e->behaviors['commerce-shipmondo.attributes'] = ShippingMethodBehavior::class;
            }
        );
    }
}
