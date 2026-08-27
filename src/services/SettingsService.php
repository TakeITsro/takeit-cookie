<?php

namespace takeit\takeitcookie\services;

use craft\helpers\Json;
use takeit\takeitcookie\models\Settings;
use takeit\takeitcookie\records\SettingsRecord;
use yii\base\Component;

/**
 * Reads and writes the single settings row.
 */
class SettingsService extends Component
{
    private ?Settings $_settings = null;

    /**
     * Stored settings, falling back to the model defaults on a fresh install.
     */
    public function getSettings(): Settings
    {
        if ($this->_settings !== null) {
            return $this->_settings;
        }

        $settings = new Settings();
        $record = $this->getRecord();

        if ($record !== null && $record->settings) {
            $stored = Json::decodeIfJson($record->settings);

            if (is_array($stored)) {
                $settings->setAttributes($this->filterStored($settings, $stored), false);
            }
        }

        return $this->_settings = $settings;
    }

    /**
     * Validates and persists the settings. Returns false if validation failed — read the
     * model's errors in that case.
     */
    public function saveSettings(Settings $settings): bool
    {
        if (!$settings->validate()) {
            return false;
        }

        $record = $this->getRecord() ?? new SettingsRecord();
        $record->settings = Json::encode($settings->toArray());

        if (!$record->save()) {
            return false;
        }

        $this->_settings = $settings;

        return true;
    }

    private function getRecord(): ?SettingsRecord
    {
        return SettingsRecord::find()
            ->orderBy(['id' => SORT_ASC])
            ->one();
    }

    /**
     * Keeps only attributes the model still declares, and drops nulls so typed properties
     * keep their defaults when a key was added in a later release.
     *
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private function filterStored(Settings $settings, array $stored): array
    {
        $known = array_flip($settings->attributes());
        $filtered = array_intersect_key($stored, $known);

        return array_filter($filtered, fn($value) => $value !== null);
    }
}
