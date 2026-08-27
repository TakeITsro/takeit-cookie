<?php

namespace takeit\takeitcookie;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use takeit\takeitcookie\services\SettingsService;
use yii\base\Event;

/**
 * TakeIT Cookie plugin.
 *
 * @property-read SettingsService $settingsService
 */
class Plugin extends BasePlugin
{
    /**
     * @inheritdoc
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @inheritdoc
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'settingsService' => ['class' => SettingsService::class],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->registerCpRoutes();
    }

    /**
     * Settings are stored in the plugin's own table rather than in project config, so the
     * Settings → Plugins entry just forwards to the Cookies section.
     *
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('takeit-cookie'));
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($item !== null) {
            $item['label'] = Craft::t('takeit-cookie', 'Cookies');
        }

        return $item;
    }

    public function getSettingsService(): SettingsService
    {
        return $this->get('settingsService');
    }

    private function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['takeit-cookie'] = 'takeit-cookie/settings/index';
                $event->rules['takeit-cookie/save'] = 'takeit-cookie/settings/save';
            }
        );
    }
}
