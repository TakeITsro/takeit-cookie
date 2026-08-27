<?php

namespace takeit\takeitcookie\services;

use Craft;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\web\View;
use takeit\takeitcookie\models\Settings;
use takeit\takeitcookie\Plugin;
use Twig\Markup;
use yii\base\Component;

/**
 * Renders the front-end output: the consent runtime for the <head>, and the banner itself.
 */
class RenderService extends Component
{
    /**
     * Template root the plugin's front-end templates live under.
     */
    public const TEMPLATE_ROOT = '_takeit-cookie';

    /**
     * A site can override any of these by adding `templates/takeit-cookie/<name>.twig`.
     */
    public const OVERRIDE_ROOT = 'takeit-cookie';

    /**
     * The consent runtime. Must be the first thing in <head>, before any script that
     * depends on consent.
     */
    public function head(): Markup
    {
        $settings = $this->getSettings();

        if (!$settings->enabled) {
            return Template::raw('');
        }

        $analytics = $this->analyticsConfig($settings);
        $pixel = $this->pixelConfig($settings);

        return $this->render('head', [
            'settings' => $settings,
            'configJson' => $this->encode($this->config($settings)),
            'analyticsJson' => $analytics !== null ? $this->encode($analytics) : null,
            'pixelJson' => $pixel !== null ? $this->encode($pixel) : null,
            'headScripts' => $this->scriptsFor($settings, [Settings::POSITION_HEAD]),
        ]);
    }

    /**
     * The banner markup, plus the script that drives it. Belongs just before </body>.
     */
    public function banner(): Markup
    {
        $settings = $this->getSettings();

        if (!$settings->enabled) {
            return Template::raw('');
        }

        return $this->render('banner', [
            'settings' => $settings,
            'items' => $this->items($settings),
            'policyUrl' => $settings->policyUrl !== '' ? $settings->policyUrl : null,
            'scriptUrl' => $this->scriptUrl(),
            'bodyScripts' => $this->scriptsFor($settings, [
                Settings::POSITION_BODY_START,
                Settings::POSITION_BODY_END,
            ]),
        ]);
    }

    /**
     * The config handed to the JavaScript runtime.
     *
     * @return array<string, mixed>
     */
    public function config(Settings $settings): array
    {
        $catalog = Settings::categoryCatalog();
        $active = $settings->activeCategoryHandles();

        $required = [];
        $children = [];

        foreach ($active as $handle) {
            if ($catalog[$handle]['required']) {
                $required[] = $handle;
            }

            $parent = $catalog[$handle]['parent'];

            if ($parent !== null) {
                $children[$parent][] = $handle;
            }
        }

        return [
            'cookieName' => $settings->cookieName,
            'version' => $settings->consentVersion,
            'lifetimeDays' => $settings->lifetimeDays,
            'revealDelay' => $settings->revealDelay,
            'badge' => $settings->badgeEnabled,
            'categories' => $active,
            'required' => $required,
            // Cast so an empty map encodes as {} rather than [].
            'children' => (object)$children,
        ];
    }

    /**
     * The top-level banner rows, each with its sub-items.
     *
     * @return array<int, array<string, mixed>>
     */
    private function items(Settings $settings): array
    {
        $catalog = Settings::categoryCatalog();
        $active = $settings->activeCategoryHandles();
        $items = [];

        foreach ($active as $handle) {
            if ($catalog[$handle]['parent'] !== null) {
                continue;
            }

            $children = [];

            foreach ($active as $childHandle) {
                if ($catalog[$childHandle]['parent'] === $handle) {
                    $children[] = [
                        'handle' => $childHandle,
                        'label' => Settings::categoryLabel($childHandle),
                    ];
                }
            }

            $items[] = [
                'handle' => $handle,
                'label' => Settings::categoryLabel($handle),
                'description' => Settings::categoryDescription($handle),
                'required' => $catalog[$handle]['required'],
                'children' => $children,
            ];
        }

        return $items;
    }

    /**
     * Everything the Google tags need, or null when nothing should ever load.
     *
     * The analytics category has to be switched on: with hard blocking there is no way to
     * consent to a category the banner never asks about, so the tag could never load.
     *
     * @return array<string, mixed>|null
     */
    public function analyticsConfig(Settings $settings): ?array
    {
        if (!$settings->isCategoryEnabled('analytics')) {
            return null;
        }

        $measurementId = $settings->resolvedGaMeasurementId();
        $containerId = $settings->resolvedGtmContainerId();

        if ($measurementId === null && $containerId === null) {
            return null;
        }

        return [
            'category' => 'analytics',
            'measurementId' => $measurementId,
            'containerId' => $containerId,
            'signals' => self::consentSignals(),
        ];
    }

    /**
     * Meta Pixel config, or null when nothing should ever load.
     *
     * @return array<string, mixed>|null
     */
    public function pixelConfig(Settings $settings): ?array
    {
        if (!$settings->isCategoryEnabled('marketing')) {
            return null;
        }

        $pixelId = $settings->resolvedMetaPixelId();

        if ($pixelId === null) {
            return null;
        }

        return [
            'category' => 'marketing',
            'pixelId' => $pixelId,
        ];
    }

    /**
     * Enabled custom scripts for the given page positions, skipping any whose category is
     * no longer being asked about.
     *
     * @param string[] $positions
     * @return array<int, array<string, mixed>>
     */
    public function scriptsFor(Settings $settings, array $positions): array
    {
        $rows = [];

        foreach ($settings->scripts as $script) {
            if (empty($script['enabled'])) {
                continue;
            }

            if (!in_array($script['position'] ?? '', $positions, true)) {
                continue;
            }

            $category = (string)($script['category'] ?? '');

            if ($category === '' || !$settings->isCategoryEnabled($category)) {
                continue;
            }

            $rows[] = $script;
        }

        return $rows;
    }

    /**
     * Names of enabled scripts that can never run, because the category they are tied to is
     * switched off. Used to warn in the control panel.
     *
     * @return string[]
     */
    public function orphanedScriptNames(Settings $settings): array
    {
        $names = [];

        foreach ($settings->scripts as $script) {
            if (empty($script['enabled'])) {
                continue;
            }

            $category = (string)($script['category'] ?? '');

            if ($category === '' || !$settings->isCategoryEnabled($category)) {
                $names[] = (string)($script['name'] ?? '');
            }
        }

        return $names;
    }

    /**
     * The Google Consent Mode v2 signals the catalogue maps to.
     *
     * @return string[]
     */
    public static function consentSignals(): array
    {
        $signals = [];

        foreach (Settings::categoryCatalog() as $definition) {
            if ($definition['gcm'] !== null) {
                $signals[] = $definition['gcm'];
            }
        }

        return $signals;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encode(array $value): string
    {
        return Json::encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Publishes banner.js and returns its URL, cache-busted on the file's own timestamp.
     */
    private function scriptUrl(): string
    {
        $dir = dirname(__DIR__) . '/web/assets/banner';

        $url = Craft::$app->getAssetManager()->getPublishedUrl($dir, true, 'banner.js');

        if ($url === false) {
            return '';
        }

        $stamp = @filemtime($dir . '/banner.js') ?: 0;

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $stamp;
    }

    /**
     * Renders one of the plugin's front-end templates, letting the site override it.
     *
     * @param array<string, mixed> $variables
     */
    private function render(string $template, array $variables): Markup
    {
        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();

        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        try {
            $override = self::OVERRIDE_ROOT . '/' . $template;

            $name = $view->doesTemplateExist($override)
                ? $override
                : self::TEMPLATE_ROOT . '/' . $template;

            $html = $view->renderTemplate($name, $variables);
        } finally {
            $view->setTemplateMode($oldMode);
        }

        return Template::raw($html);
    }

    private function getSettings(): Settings
    {
        return Plugin::getInstance()->getSettingsService()->getSettings();
    }
}
