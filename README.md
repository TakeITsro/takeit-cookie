# TakeIT Cookie

Cookie consent banner for Craft 5.

Google Analytics, Google Tag Manager, the Meta Pixel and any custom snippet stay hard
blocked until the visitor accepts the matching category. Nothing third-party is requested
before consent — no script tag, no cookieless ping, no beacon.

- Vanilla JavaScript. No Alpine, no js-cookie, no jQuery.
- Ships no CSS, and uses class names you can style yourself.
- Settings live in the plugin's own table, so they stay editable on production.
- Consent is one versioned cookie; raise the version to re-ask everyone.

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later

## Installing

The plugin is not on Packagist. Point Composer at the repository from your Craft project's
`composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/TakeITsro/takeit-cookie.git"
        }
    ]
}
```

Then, from the Craft project root:

```bash
composer require takeit/takeit-cookie:^1.0.0
```

```bash
php craft plugin/install takeit-cookie
```

The repository is private, so any server installing it needs a GitHub token in its Composer
auth config.

### Working on the plugin locally

To edit the plugin and see changes immediately, use a path repository instead:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/absolute/path/to/takeit-cookie",
            "options": { "symlink": true }
        }
    ]
}
```

```bash
composer require takeit/takeit-cookie:@dev
```

Local development only — a symlinked path repository cannot be deployed.

## Adding it to your templates

Two calls.

```twig
<head>
    {{ craft.takeitCookie.head() }}
    ...
</head>
<body>
    ...
    {{ craft.takeitCookie.banner() }}
</body>
```

`head()` must come before any script of yours that touches `dataLayer` or defines `gtag`,
otherwise your code wins and the consent defaults land too late. Put it first.

`banner()` goes just before `</body>`.

## Migrating from a hand-rolled banner

If the site already has the older TakeIT banner, remove it in this order.

1. Add the two calls above to your layout.
2. Delete the banner include — `blocks/cookie.twig` or `blocks/cookies.twig` — and the
   `{% include "blocks/cookie" %}` line.
3. Delete `components/body/ga_default.twig` and `components/body/ga_code.twig`, and their
   `{% include %}` lines.
4. Drop the `ga` global variable those templates depended on, if nothing else uses it.
5. Move the GA measurement ID out of the old hardcoded `gtag('config', 'G-…')` call and into
   the **Analytics** tab.
6. Keep `_cookie.scss` as it is. Optionally remove the `5s` from
   `transition: all 0.3s ease 5s`, since the reveal delay is now set from the control panel
   and overrides it.

Existing visitors are asked again — the plugin does not read the old `cookies` cookie, and
the old `consent-used` key in localStorage is simply left behind, harmless.

## Settings

Everything is under **Cookies** in the control-panel nav. Settings are stored in the
plugin's own table, not project config, so they can be changed on production without
`allowAdminChanges`.

### General

| Setting | Notes |
| --- | --- |
| Enable the cookie banner | Off means no banner and no gated script loads at all |
| Company name | Banner heading. Leave empty to omit it |
| Cookie policy URL | Linked from the banner text. Site-relative or absolute |
| Consent cookie name | Default `cookie_consent` |
| Consent lifetime | Days before the visitor is asked again. Default 365 |
| Consent version | Raise it to invalidate every stored answer |
| Reveal delay | Seconds after load before the banner appears. Overrides the CSS delay |
| Show the re-open badge | The permanent handle for changing an answer |
| Badge label | Text inside the badge |

### Categories

Which consent categories the banner asks about. Anything switched off here is not shown and
not stored.

| Handle | Notes |
| --- | --- |
| `functional` | Always on, locked, cannot be declined |
| `analytics` | Gates GA4 and GTM |
| `ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage` | Google Consent Mode v2 signals, shown as sub-items under analytics |
| `marketing` | Gates the Meta Pixel. Off by default |

A sub-item switched on under a switched-off `analytics` resolves to off.

### Analytics — Google Analytics and Tag Manager

Set the GA4 measurement ID and/or the GTM container ID. Both are gated behind **analytics**:

- No decision yet, or declined — nothing is requested from Google.
- Accepted on an earlier visit — the tags load in `<head>`, as early as they would without
  the plugin.
- Accepted on the banner — the tags are injected immediately, no reload.
- Withdrawn — the page reloads, and the tags are not injected on the way back.

Consent Mode v2 runs alongside the hard block: `consent default` is pushed before anything
loads, `consent update` on every change. Accepting analytics while declining personalised
advertising loads GA with `ad_personalization: denied`.

A signal you switch off under **Categories** is not asked about separately and follows the
analytics answer — not Google's own defaults, which are *granted*.

### Marketing — Meta Pixel

Gated behind **marketing**, which is off by default. `fbevents.js` is not requested until
marketing is granted; on grant the pixel bootstraps, calls `fbq('consent', 'grant')`, inits
and tracks a `PageView`.

There is no `<noscript>` fallback pixel. An image beacon cannot be consent-gated, so it is
left out on purpose.

### Scripts — everything else

Any third-party snippet — chat widgets, other pixels, heatmaps — tied to a category and a
page position. Paste the snippet exactly as the vendor gives it, `<script>` tags and all.

Until its category is granted, a snippet sits in an inert `<template>`: parsed, but nothing
runs and nothing is fetched. On grant its nodes are moved into the page and every `<script>`
is recreated so it executes, in order. No reload, and it cannot run twice.

Position picks where the nodes land — `head`, the start of `<body>`, or the end of it.
Head-position snippets are emitted by `head()`, the other two by `banner()`.

> **This field executes arbitrary JavaScript on every front-end page.** Anyone with control
> panel access to the plugin can inject it. Treat the `accessPlugin-takeit-cookie`
> permission with the same care as template access.

### Environment variables

Every ID field accepts one, e.g. `$GA_ID`, so staging and production can differ without
changing the stored settings.

## For your own JavaScript

```js
if (window.TakeitCookie.has('analytics')) {
    // ...
}

window.TakeitCookie.onChange(function (result) {
    // result.values  — every category, 1 or 0
    // result.revoked — true when something was switched off
});
```

| Method | Returns |
| --- | --- |
| `has(handle)` | Whether that category is granted |
| `get()` | Every category as `1` or `0` |
| `isDecided()` | Whether a valid answer is stored |
| `save(values)` | Writes consent, returns `{values, revoked}` |
| `onChange(fn)` | Called after every change |
| `config` | The settings handed to the front end |

Consent is one cookie: `{"v":1,"ts":1756000000,"c":{"functional":1,"analytics":0}}`, with
`SameSite=Lax` and `Secure` over https. A cookie whose `v` is below the current consent
version counts as no answer at all.

## Caching

The plugin never varies its HTML by consent — the same markup is served to every visitor and
all gating happens in JavaScript. Full-page caches (Blitz, Cloudflare, `{% cache %}`) are
therefore safe.

## Overriding the markup

Add `templates/takeit-cookie/banner.twig` to your site and the plugin renders yours instead
of its own. `head.twig` can be overridden the same way, though it is machinery rather than
markup.

`banner.twig` receives `settings`, `items`, `policyUrl`, `scriptUrl` and `bodyScripts`.

## Styling

The plugin ships no CSS. The markup uses the class names the hand-rolled banner used, so an
existing stylesheet keeps working:

`.cookie`, `.cookie.visible`, `.cookie_wrapper`, `.cookie_title`, `.cookie_text`,
`.cookie_container`, `.cookie_wrapper_item`, `.cookie_wrapper_line`, `.cookie_wrapper_sub`,
`.cookie_wrapper_sub_item`, `.cookie_subtitle`, `.cookie_checkbox`, `.cookie_checkbox.active`,
`.cookie_checkbox.disabled`, `.cookie_bottom`, `.cookie_more`, `.cookie_less`, `.cookie_deny`,
`.cookie_save`, `.cookie_accept`, `.cookie_badge`, `.cookie_badge.visible`.

Visibility is driven by the `visible` class on `.cookie` and `.cookie_badge`. The detail
panel and the buttons are toggled with inline `display`.

## Translations

Banner copy ships with the plugin in English (source), Hungarian and Slovak, under the
`takeit-cookie` translation category. Control panel strings are English only.

To adjust wording for one site, add `translations/<language>/takeit-cookie.php` to the Craft
project — plugin translations allow overrides.

## Troubleshooting

**The banner never appears.** Check that the banner is enabled, that `banner()` is in the
layout, and that consent is not already stored — clear the consent cookie, or raise the
consent version.

**Google Analytics never loads.** It only loads once analytics is accepted. Check the
analytics category is switched on under **Categories**; the Analytics tab warns when an ID
is set while the category is off.

**A custom script never runs.** Its category has to be switched on and accepted, and the
row itself has to be switched on. The Scripts tab lists rows whose category is no longer
being asked about.

**`gtag` is already defined.** Move `craft.takeitCookie.head()` above your own scripts. The
plugin will not overwrite an existing `window.gtag`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
