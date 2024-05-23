<?php

namespace QD\commerce\shipmondo\migrations;

use craft\db\Migration;
use QD\commerce\shipmondo\plugin\Table;

class m240521_154000_shippingmethods_renameOwnAgreement extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists(Table::SHIPPINGMETHODS, 'ownAgreement')) {
            return true;
        }

        if (!$this->db->columnExists(Table::SHIPPINGMETHODS, 'useOwnAgreement')) {
            return true;
        }

        $this->renameColumn(Table::SHIPPINGMETHODS, 'useOwnAgreement', 'ownAgreement');
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240521_154000_shippingmethods_renameOwnAgreement cannot be reverted.\n";
        return false;
    }
}
