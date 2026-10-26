# Changelog

## 1.1.2 - 2026-10-26

### Added

- Separate Badge background and Badge text colour fields, so the re-open badge can be
  coloured independently of the banner. Left empty it renders exactly as before.

## 1.1.1 - 2026-10-26

### Added

- The plugin now ships its own stylesheet and loads it by default, so the banner is styled
  without the host site providing anything. It is self-contained — no build step, no
  dependency on the site's utility classes, and its own palette.
- **Load the plugin stylesheet** setting on the General tab, on by default.
- Background, Text, Main and Decline colour fields on the General tab. Left empty they use
  the shipped defaults; set, they are emitted as custom properties scoped to the banner.
- Plugin icon, and a matching monochrome nav icon for the control panel sidebar.

### Changed

- The stylesheet link is emitted before the site's own stylesheets, so site rules win on
  equal specificity when both are loaded.
- The banner no longer inherits the host site's colour variables. It ships its own palette,
  so it cannot paint a button in the panel's own colour on a site whose variables mean
  something different.
- Buttons now split by role rather than by the class names in the markup: Accept all and
  Save carry the main colour, while Detail, Close and Decline all are neutral and always
  contrast against the panel.

### Fixed

- The **Add a script** button did nothing. Craft's editable tables only initialise while
  visible and afterwards retry only on a window resize, so a table in a background tab never
  bound its add-row handler. The Scripts table is now initialised as soon as its tab is
  shown.

## 1.0.0 - 2026-08-27

Initial release.

### Added

- Cookie consent banner for Craft 5, rendered from `craft.takeitCookie.head()` and
  `craft.takeitCookie.banner()`.
- Vanilla-JS consent runtime on `window.TakeitCookie` — no Alpine, no js-cookie. Consent is
  stored in one versioned cookie with `SameSite=Lax`, and `Secure` over https.
- Raising the consent version invalidates every stored answer and re-prompts.
- Withdrawing consent reloads the page, so scripts that are already running stop.
- Google Analytics 4 and Tag Manager, hard-blocked behind the analytics category. Nothing is
  requested from Google before consent, and the tags load without a reload once it is given.
- Google Consent Mode v2 signals pushed as `default` and `update`. A signal that is not
  asked about separately follows the analytics answer.
- Meta Pixel, hard-blocked behind the marketing category.
- Custom scripts, each tied to a category and a page position. Snippets are held in an inert
  `<template>` until their category is granted, then their nodes are moved into the page
  with `<script>` elements recreated so they execute in order.
- Control panel section with General, Categories, Analytics, Marketing and Scripts tabs.
  Settings are stored in the plugin's own table rather than project config, so they stay
  editable on production.
- Categories can be switched on and off individually; sub-categories resolve to off when
  their parent is off.
- Warnings for configurations that can never work: a Google or pixel ID set while its
  category is switched off, and enabled scripts tied to a category that is no longer asked
  about.
- Every ID field accepts an environment variable.
- Banner copy in English, Hungarian and Slovak.
- Site-side template overrides at `templates/takeit-cookie/banner.twig`.
- No CSS ships with the plugin; the markup reuses the class names of the hand-rolled banner
  it replaces.
