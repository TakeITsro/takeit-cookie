<?php

namespace takeit\takeitcookie\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;

/**
 * Plugin settings. Persisted as JSON in {{%takeitcookie_settings}} — deliberately not in
 * project config, so they stay editable in the control panel on production.
 */
class Settings extends Model
{
    public const POSITION_HEAD = 'head';
    public const POSITION_BODY_START = 'bodyStart';
    public const POSITION_BODY_END = 'bodyEnd';

    // General
    // -------------------------------------------------------------------------

    public bool $enabled = true;

    /** Shown as the banner heading. */
    public string $companyName = '';

    /** Absolute or site-relative URL of the cookie policy page. */
    public string $policyUrl = '';

    public string $cookieName = 'cookie_consent';

    public int $lifetimeDays = 365;

    /**
     * Bump this to invalidate every stored consent and re-prompt all visitors.
     */
    public int $consentVersion = 1;

    /** Seconds to wait after page load before the banner appears. */
    public int $revealDelay = 5;

    public bool $badgeEnabled = true;

    public string $badgeLabel = 'Cookies';

    // Categories
    // -------------------------------------------------------------------------

    /**
     * Category handle => whether it is shown in the banner. `functional` is always on.
     *
     * @var array<string, bool>
     */
    public array $enabledCategories = [
        'functional' => true,
        'analytics' => true,
        'ad_storage' => true,
        'ad_user_data' => true,
        'ad_personalization' => true,
        'analytics_storage' => true,
        'marketing' => false,
    ];

    // Analytics
    // -------------------------------------------------------------------------

    public string $gaMeasurementId = '';

    public string $gtmContainerId = '';

    // Marketing
    // -------------------------------------------------------------------------

    public string $metaPixelId = '';

    // Custom scripts
    // -------------------------------------------------------------------------

    /**
     * @var array<int, array{name: string, category: string, position: string, code: string, enabled: bool}>
     */
    public array $scripts = [];

    /**
     * The fixed catalogue of consent categories.
     *
     * `parent` nests a category underneath another one in the banner; `gcm` is the Google
     * Consent Mode v2 signal the category maps to, if any.
     *
     * @return array<string, array{parent: string|null, required: bool, gcm: string|null}>
     */
    public static function categoryCatalog(): array
    {
        return [
            'functional' => ['parent' => null, 'required' => true, 'gcm' => null],
            'analytics' => ['parent' => null, 'required' => false, 'gcm' => null],
            'ad_storage' => ['parent' => 'analytics', 'required' => false, 'gcm' => 'ad_storage'],
            'ad_user_data' => ['parent' => 'analytics', 'required' => false, 'gcm' => 'ad_user_data'],
            'ad_personalization' => ['parent' => 'analytics', 'required' => false, 'gcm' => 'ad_personalization'],
            'analytics_storage' => ['parent' => 'analytics', 'required' => false, 'gcm' => 'analytics_storage'],
            'marketing' => ['parent' => null, 'required' => false, 'gcm' => null],
        ];
    }

    /**
     * Translated banner label for a category.
     */
    public static function categoryLabel(string $handle): string
    {
        return match ($handle) {
            'functional' => Craft::t('takeit-cookie', 'Functional cookies'),
            'analytics' => Craft::t('takeit-cookie', 'Analytics cookies'),
            'ad_storage' => Craft::t('takeit-cookie', 'Consent to storing cookies needed for Google Ads'),
            'ad_user_data' => Craft::t('takeit-cookie', 'Consent to sending data for Google Ads purposes'),
            'ad_personalization' => Craft::t('takeit-cookie', 'Consent to personalised advertising'),
            'analytics_storage' => Craft::t('takeit-cookie', 'Consent to storing Google Analytics cookies'),
            'marketing' => Craft::t('takeit-cookie', 'Marketing cookies'),
            default => $handle,
        };
    }

    /**
     * Translated banner description for a category. Sub-items have no separate description.
     */
    public static function categoryDescription(string $handle): string
    {
        return match ($handle) {
            'functional' => Craft::t('takeit-cookie', 'These cookies are required for the website to work correctly.'),
            'analytics' => Craft::t('takeit-cookie', 'To improve our services we use Google Analytics, which sends anonymous information about your visit and collects aggregated data about visitor habits.'),
            'marketing' => Craft::t('takeit-cookie', 'These cookies let us measure the performance of our advertising and show you more relevant ads.'),
            default => '',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function positionOptions(): array
    {
        return [
            self::POSITION_HEAD => Craft::t('takeit-cookie', 'Head'),
            self::POSITION_BODY_START => Craft::t('takeit-cookie', 'Body start'),
            self::POSITION_BODY_END => Craft::t('takeit-cookie', 'Body end'),
        ];
    }

    /**
     * Whether a category should be rendered in the banner.
     *
     * `functional` is always on. A sub-category is only on when its parent is too.
     */
    public function isCategoryEnabled(string $handle): bool
    {
        $catalog = self::categoryCatalog();

        if (!isset($catalog[$handle])) {
            return false;
        }

        if ($catalog[$handle]['required']) {
            return true;
        }

        if (empty($this->enabledCategories[$handle])) {
            return false;
        }

        $parent = $catalog[$handle]['parent'];

        return $parent === null || $this->isCategoryEnabled($parent);
    }

    /**
     * Handles of every category that is actually shown, in catalogue order.
     *
     * @return string[]
     */
    public function activeCategoryHandles(): array
    {
        return array_values(array_filter(
            array_keys(self::categoryCatalog()),
            fn(string $handle) => $this->isCategoryEnabled($handle)
        ));
    }

    /**
     * Categories a custom script can be tied to — the top-level, non-required ones.
     *
     * @return array<string, string>
     */
    public function scriptCategoryOptions(): array
    {
        $options = [];

        foreach (self::categoryCatalog() as $handle => $definition) {
            if ($definition['required'] || $definition['parent'] !== null) {
                continue;
            }

            if (!$this->isCategoryEnabled($handle)) {
                continue;
            }

            $options[$handle] = self::categoryLabel($handle);
        }

        return $options;
    }

    public function resolvedGaMeasurementId(): ?string
    {
        return App::parseEnv($this->gaMeasurementId) ?: null;
    }

    public function resolvedGtmContainerId(): ?string
    {
        return App::parseEnv($this->gtmContainerId) ?: null;
    }

    public function resolvedMetaPixelId(): ?string
    {
        return App::parseEnv($this->metaPixelId) ?: null;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'enabled' => Craft::t('takeit-cookie', 'Enable the cookie banner'),
            'companyName' => Craft::t('takeit-cookie', 'Company name'),
            'policyUrl' => Craft::t('takeit-cookie', 'Cookie policy URL'),
            'cookieName' => Craft::t('takeit-cookie', 'Consent cookie name'),
            'lifetimeDays' => Craft::t('takeit-cookie', 'Consent lifetime (days)'),
            'consentVersion' => Craft::t('takeit-cookie', 'Consent version'),
            'revealDelay' => Craft::t('takeit-cookie', 'Reveal delay (seconds)'),
            'badgeEnabled' => Craft::t('takeit-cookie', 'Show the re-open badge'),
            'badgeLabel' => Craft::t('takeit-cookie', 'Badge label'),
            'gaMeasurementId' => Craft::t('takeit-cookie', 'GA4 measurement ID'),
            'gtmContainerId' => Craft::t('takeit-cookie', 'GTM container ID'),
            'metaPixelId' => Craft::t('takeit-cookie', 'Meta Pixel ID'),
            'scripts' => Craft::t('takeit-cookie', 'Custom scripts'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['enabled', 'badgeEnabled'], 'boolean'],
            [
                [
                    'companyName',
                    'policyUrl',
                    'cookieName',
                    'badgeLabel',
                    'gaMeasurementId',
                    'gtmContainerId',
                    'metaPixelId',
                ],
                'trim',
            ],
            [['cookieName'], 'required'],
            [
                ['cookieName'],
                'match',
                'pattern' => '/^[A-Za-z0-9_\-]+$/',
                'message' => Craft::t('takeit-cookie', 'Use letters, numbers, underscores and hyphens only.'),
            ],
            [['lifetimeDays'], 'integer', 'min' => 1, 'max' => 3650],
            [['consentVersion'], 'integer', 'min' => 1],
            [['revealDelay'], 'integer', 'min' => 0, 'max' => 60],
            [
                ['gaMeasurementId'],
                'match',
                'pattern' => '/^(\$[A-Za-z0-9_]+|G-[A-Za-z0-9]+)$/',
                'skipOnEmpty' => true,
                'message' => Craft::t('takeit-cookie', 'Expected a GA4 measurement ID such as G-XXXXXXXXXX, or an environment variable such as $GA_ID.'),
            ],
            [
                ['gtmContainerId'],
                'match',
                'pattern' => '/^(\$[A-Za-z0-9_]+|GTM-[A-Za-z0-9]+)$/',
                'skipOnEmpty' => true,
                'message' => Craft::t('takeit-cookie', 'Expected a GTM container ID such as GTM-XXXXXXX, or an environment variable such as $GTM_ID.'),
            ],
            [
                ['metaPixelId'],
                'match',
                'pattern' => '/^(\$[A-Za-z0-9_]+|[0-9]{6,25})$/',
                'skipOnEmpty' => true,
                'message' => Craft::t('takeit-cookie', 'Expected a numeric Meta Pixel ID, or an environment variable such as $META_PIXEL_ID.'),
            ],
            [['enabledCategories'], 'validateEnabledCategories'],
            [['scripts'], 'validateScripts'],
            [
                [
                    'enabled',
                    'companyName',
                    'policyUrl',
                    'cookieName',
                    'lifetimeDays',
                    'consentVersion',
                    'revealDelay',
                    'badgeEnabled',
                    'badgeLabel',
                    'enabledCategories',
                    'gaMeasurementId',
                    'gtmContainerId',
                    'metaPixelId',
                    'scripts',
                ],
                'safe',
            ],
        ];
    }

    public function validateEnabledCategories(string $attribute): void
    {
        $catalog = self::categoryCatalog();
        $clean = [];

        foreach ($catalog as $handle => $definition) {
            $clean[$handle] = $definition['required']
                ? true
                : !empty($this->enabledCategories[$handle]);
        }

        $this->enabledCategories = $clean;
    }

    public function validateScripts(string $attribute): void
    {
        $catalog = self::categoryCatalog();
        $positions = self::positionOptions();
        $clean = [];

        foreach ($this->scripts as $index => $script) {
            if (!is_array($script)) {
                continue;
            }

            $name = trim((string)($script['name'] ?? ''));
            $code = trim((string)($script['code'] ?? ''));

            // Skip rows the editor left completely blank.
            if ($name === '' && $code === '') {
                continue;
            }

            $category = (string)($script['category'] ?? '');
            $position = (string)($script['position'] ?? '');

            if ($name === '') {
                $this->addError($attribute, Craft::t('takeit-cookie', 'Row {row}: give the script a name.', ['row' => $index + 1]));
            }

            if ($code === '') {
                $this->addError($attribute, Craft::t('takeit-cookie', 'Row {row}: the script is empty.', ['row' => $index + 1]));
            }

            if (!isset($catalog[$category]) || $catalog[$category]['required'] || $catalog[$category]['parent'] !== null) {
                $this->addError($attribute, Craft::t('takeit-cookie', 'Row {row}: pick a consent category that visitors can decline.', ['row' => $index + 1]));
                $category = '';
            }

            if (!isset($positions[$position])) {
                $position = self::POSITION_BODY_END;
            }

            $clean[] = [
                'name' => $name,
                'category' => $category,
                'position' => $position,
                'code' => $code,
                'enabled' => !empty($script['enabled']),
            ];
        }

        $this->scripts = $clean;
    }
}
