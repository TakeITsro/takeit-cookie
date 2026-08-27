<?php

namespace takeit\takeitcookie\variables;

use takeit\takeitcookie\models\Settings;
use takeit\takeitcookie\Plugin;
use Twig\Markup;

/**
 * Exposed to site templates as `craft.takeitCookie`.
 */
class CookieVariable
{
    /**
     * The consent runtime. Output this as the first thing inside <head>.
     */
    public function head(): Markup
    {
        return Plugin::getInstance()->getRenderService()->head();
    }

    /**
     * The banner. Output this just before </body>.
     */
    public function banner(): Markup
    {
        return Plugin::getInstance()->getRenderService()->banner();
    }

    /**
     * Plugin settings, for templates that need to branch on them.
     */
    public function settings(): Settings
    {
        return Plugin::getInstance()->getSettingsService()->getSettings();
    }
}
