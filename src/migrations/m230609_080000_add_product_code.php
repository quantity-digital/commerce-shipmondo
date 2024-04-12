<?php

/**
 * Migration
 * Add new columns to shipping methods table
 */

namespace QD\commerce\shipmondo\migrations;

use craft\db\Migration;
use QD\commerce\shipmondo\plugin\Table;

class m230609_080000_add_product_code extends Migration
{
    public function safeUp()
    {
        $this->alterTables();
    }

    public function safeDown()
    {
        echo "m230609_080000_add_product_code cannot be reverted";
        return false;
    }

    protected function alterTables()
    {
        $this->addColumn(
            Table::SHIPPINGMETHODS,
            'productCode',
            $this->string()->null(),
        );

        $this->addColumn(
            Table::SHIPPINGMETHODS,
            'useOwnAgreement',
            $this->boolean()->defaultValue(false),
        );
    }
}
