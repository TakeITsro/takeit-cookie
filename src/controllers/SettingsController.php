<?php

namespace takeit\takeitcookie\controllers;

use Craft;
use craft\web\Controller;
use takeit\takeitcookie\models\Settings;
use takeit\takeitcookie\Plugin;
use yii\web\Response;

class SettingsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('accessPlugin-takeit-cookie');

        return true;
    }

    /**
     * The Cookies settings screen.
     *
     * @param Settings|null $settings Populated by the save action when validation failed,
     *                                so the editor gets their input and errors back.
     */
    public function actionIndex(?Settings $settings = null): Response
    {
        $settings ??= Plugin::getInstance()->getSettingsService()->getSettings();

        return $this->renderTemplate('takeit-cookie/index.twig', [
            'settings' => $settings,
            'catalog' => Settings::categoryCatalog(),
            'categoryLabels' => $this->categoryLabels(),
            'categoryDescriptions' => $this->categoryDescriptions(),
            'positionOptions' => $this->selectOptions(Settings::positionOptions()),
            'scriptCategoryOptions' => $this->selectOptions($settings->scriptCategoryOptions()),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $service = Plugin::getInstance()->getSettingsService();
        $settings = $service->getSettings();

        $this->populate($settings);

        if (!$service->saveSettings($settings)) {
            $this->setFailFlash(Craft::t('takeit-cookie', 'Couldn’t save settings.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $settings,
            ]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('takeit-cookie', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Category labels keyed by handle, so the template can look them up.
     *
     * @return array<string, string>
     */
    private function categoryLabels(): array
    {
        $labels = [];

        foreach (array_keys(Settings::categoryCatalog()) as $handle) {
            $labels[$handle] = Settings::categoryLabel($handle);
        }

        return $labels;
    }

    /**
     * Category descriptions keyed by handle. Sub-categories have none.
     *
     * @return array<string, string>
     */
    private function categoryDescriptions(): array
    {
        $descriptions = [];

        foreach (array_keys(Settings::categoryCatalog()) as $handle) {
            $descriptions[$handle] = Settings::categoryDescription($handle);
        }

        return $descriptions;
    }

    /**
     * Turns a value => label map into the {label, value} list the editable table expects.
     *
     * @param array<string, string> $map
     * @return array<int, array{label: string, value: string}>
     */
    private function selectOptions(array $map): array
    {
        $options = [];

        foreach ($map as $value => $label) {
            $options[] = ['label' => $label, 'value' => (string)$value];
        }

        return $options;
    }

    private function populate(Settings $settings): void
    {
        $request = Craft::$app->getRequest();

        $settings->enabled = (bool)$request->getBodyParam('enabled');
        $settings->companyName = (string)$request->getBodyParam('companyName', '');
        $settings->policyUrl = (string)$request->getBodyParam('policyUrl', '');
        $settings->cookieName = (string)$request->getBodyParam('cookieName', '');
        $settings->lifetimeDays = (int)$request->getBodyParam('lifetimeDays', 365);
        $settings->consentVersion = (int)$request->getBodyParam('consentVersion', 1);
        $settings->revealDelay = (int)$request->getBodyParam('revealDelay', 0);
        $settings->badgeEnabled = (bool)$request->getBodyParam('badgeEnabled');
        $settings->badgeLabel = (string)$request->getBodyParam('badgeLabel', '');

        $settings->gaMeasurementId = (string)$request->getBodyParam('gaMeasurementId', '');
        $settings->gtmContainerId = (string)$request->getBodyParam('gtmContainerId', '');
        $settings->metaPixelId = (string)$request->getBodyParam('metaPixelId', '');

        $postedCategories = $request->getBodyParam('enabledCategories', []);
        $categories = [];

        foreach (Settings::categoryCatalog() as $handle => $definition) {
            $categories[$handle] = $definition['required']
                ? true
                : !empty($postedCategories[$handle]);
        }

        $settings->enabledCategories = $categories;

        // The editable table posts rows keyed by row ID, so re-index them.
        $settings->scripts = array_values((array)$request->getBodyParam('scripts', []));
    }
}
