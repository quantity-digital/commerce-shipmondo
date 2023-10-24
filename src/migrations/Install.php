<?php

namespace QD\commerce\shipmondo\migrations;

use Craft;
use craft\commerce\db\Table as CommerceTable;
use craft\db\Migration;
use QD\commerce\shipmondo\plugin\Table;

class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();
        $this->dropProjectConfig();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables for Shipments
     *
     * @return void
     */
    protected function createTables()
    {
        $this->createTable(Table::ORDERINFO, [
            'id' => $this->integer()->notNull(),
            'shipmondoId' => $this->string()->null(),
            'shipmentId' => $this->string()->null(),
            'servicePointId' => $this->string()->null(),
            'servicePointSnapshot' => $this->longText()->null(),
            'datePushed' => $this->dateTime(),
            'PRIMARY KEY([[id]])',
        ]);

        $this->createTable(Table::SHIPPINGMETHODS, [
            'id' => $this->integer()->notNull(),
            'templateId' => $this->string()->null(),
            'carrierCode' => $this->string()->null(),
            'carrierName' => $this->string()->null(),
            'productCode' => $this->string()->null(),
            'useOwnAgreement' => $this->boolean()->defaultValue(false),
            'requireServicePoint' => $this->boolean()->defaultValue(false),
            'requiredFields' => $this->string()->null(),
            'PRIMARY KEY([[id]])',
        ]);
    }

    /**
     * Drop the tables
     *
     * @return void
     */
    protected function dropTables()
    {
        $this->dropTableIfExists(Table::ORDERINFO);
        $this->dropTableIfExists(Table::SHIPPINGMETHODS);

        return null;
    }

    /**
     * Deletes the project config entry.
     */
    protected function dropProjectConfig()
    {
        Craft::$app->projectConfig->remove('commerce-shipmondo');
    }

    /**
     * Creates the indexes.
     *
     * @return void
     */
    protected function createIndexes()
    {
    }

    /**
     * Adds the foreign keys.
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey('commerce_orderId', Table::ORDERINFO, ['id'], CommerceTable::ORDERS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey('commerce_shippingmethodId', Table::SHIPPINGMETHODS, ['id'], CommerceTable::SHIPPINGMETHODS, ['id'], 'CASCADE', 'CASCADE');
    }

    /**
     * Drop the foreign keys.
     *
     * @return void
     */
    protected function dropForeignKeys()
    {
        $this->dropForeignKey('commerce_orderId', Table::ORDERINFO);
        $this->dropForeignKey('commerce_shippingmethodId', Table::SHIPPINGMETHODS);
    }
}
