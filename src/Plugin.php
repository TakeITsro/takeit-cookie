<?php

namespace takeit\takeitcookie;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use takeit\takeitcookie\services\RenderService;
use takeit\takeitcookie\services\SettingsService;
use takeit\takeitcookie\variables\CookieVariable;
use yii\base\Event;

/**
 * TakeIT Cookie plugin.
 *
 * @property-read SettingsService $settingsService
 * @property-read RenderService $renderService
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
                'renderService' => ['class' => RenderService::class],
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
        $this->registerSiteTemplateRoot();
        $this->registerTwigVariable();
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

    public function getRenderService(): RenderService
    {
        return $this->get('renderService');
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

    private function registerSiteTemplateRoot(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots[RenderService::TEMPLATE_ROOT] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . '_frontend';
            }
        );
    }

    private function registerTwigVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('takeitCookie', CookieVariable::class);
            }
        );
    }
}
