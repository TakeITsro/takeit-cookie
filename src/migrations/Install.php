<?php

namespace takeit\takeitcookie\migrations;

use craft\db\Migration;
use takeit\takeitcookie\records\SettingsRecord;

class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->tableExists(SettingsRecord::TABLE)) {
            return true;
        }

        $this->createTable(SettingsRecord::TABLE, [
            'id' => $this->primaryKey(),
            'settings' => $this->longText(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(SettingsRecord::TABLE);

        return true;
    }
}
