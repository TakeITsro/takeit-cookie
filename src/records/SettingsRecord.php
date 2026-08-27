<?php

namespace takeit\takeitcookie\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string|null $settings JSON blob of the plugin settings
 */
class SettingsRecord extends ActiveRecord
{
    public const TABLE = '{{%takeitcookie_settings}}';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return self::TABLE;
    }
}
