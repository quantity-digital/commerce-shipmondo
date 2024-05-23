<?php

namespace QD\commerce\shipmondo\migrations;

use craft\db\Migration;
use QD\commerce\shipmondo\plugin\Table;

class m240521_151500_shippingmethods_alter extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists(Table::SHIPPINGMETHODS, 'serviceCodes')) {
            return true;
        }

        $this->alterTables();
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240521_151500_shippingmethods_alter cannot be reverted.\n";
        return false;
    }

    protected function alterTables()
    {
        $this->addColumn(Table::SHIPPINGMETHODS, 'serviceCodes', $this->string()->null());
    }
}
