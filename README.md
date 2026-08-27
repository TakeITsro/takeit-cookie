# TakeIT Cookie

Cookie consent banner for Craft 5. Google Analytics, Meta Pixel and any custom script are
hard-blocked until the visitor accepts the matching category — nothing third-party is
requested before consent.

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later

## Installing

While the plugin lives outside Packagist, point Composer at the folder from your Craft
project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../path/to/takeit-cookie",
            "options": { "symlink": true }
        }
    ]
}
```

Then, from the Craft project root:

```bash
composer require takeit/takeit-cookie:@dev
php craft plugin/install takeit-cookie
```

## Settings

Everything lives under **Cookies** in the control-panel nav. Settings are stored in the
plugin's own table, not in project config, so they can be changed directly on production
without `allowAdminChanges`.

| Tab | What it holds |
| --- | --- |
| General | Banner on/off, company name, policy URL, consent cookie name and lifetime, consent version, reveal delay, re-open badge |
| Categories | Which consent categories the banner asks about |
| Analytics | GA4 measurement ID, GTM container ID |
| Marketing | Meta Pixel ID |
| Scripts | Custom snippets, each tied to a category and a page position |

Any ID field also accepts an environment variable, e.g. `$GA_ID`.

### Consent version

Raise **Consent version** whenever you change which categories you ask about. Every stored
consent below the current version is treated as missing, so visitors are asked again.

## Status

Phase 1 — the plugin installs and the control panel screen saves. Front-end output (the
banner, and the consent-gated GA / Pixel / custom-script loading) lands in the next phases.
