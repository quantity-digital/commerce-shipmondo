<?php

/**
 * Migration
 * Add new columns to shipping methods table
 */

namespace QD\commerce\shipmondo\migrations;

use craft\db\Migration;
use QD\commerce\shipmondo\plugin\Table;

class m231606_120000_add_required_fields extends Migration
{
    public function safeUp()
    {
        $this->alterTables();
    }

    public function safeDown()
    {
        echo "m231606_120000_add_required_fields cannot be reverted";
        return false;
    }

    protected function alterTables()
    {
        $this->addColumn(
            Table::SHIPPINGMETHODS,
            'requiredFields',
            $this->string()->null(),
        );
    }
}
