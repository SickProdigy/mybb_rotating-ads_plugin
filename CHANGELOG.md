# Changelog

## 0.8.1 - 2026-09-10

- Added support for site-relative image and destination paths such as `/images/sponsored/ad-hostpro.jpg`.
- Kept protocol-relative external URLs rejected so local-path support cannot bypass the HTTP(S) validation policy.

## 0.8.0 - 2026-09-10

- Added per-ad impression, click, and CTR totals with privacy-conscious tracked redirects.
- Added delivery weights, UTC start/end dates, impression limits, and click limits.
- Added country allowlist and blocklist targeting from common server-provided country headers.
- Replaced the confusing order control with delivery and metrics summaries in Admin CP.
- Kept weighted server-side selection and impression tracking functional without JavaScript.

## 0.7.2 - 2026-09-10

- Fixed the native ad form submit button rendering without a label.
- Added explicit Add ad and Save changes labels for the create and edit forms.

## 0.7.1 - 2026-09-10

- Added a read-only Template variables row to the Rotating Ads settings page for issue #6.
- Clarified that administrators choose placement with `{$rotating_ads_square}` and `{$rotating_ads_banner}`; the plugin does not inject a slot automatically.

## 0.7.0 - 2026-09-10

- Replaced the pipe-delimited Admin CP inventory textareas with a native MyBB ad manager under Configuration > Rotating Ads.
- Added database-backed ad records with stable IDs, slot, status, and display order fields.
- Migrates existing square and banner inventory settings when the plugin is activated.
- Removed the custom Admin CP JavaScript editor; public same-page rotation still uses JavaScript when enabled.

## 0.6.3 - 2026-09-09

- Moved Admin CP inventory row editor injection to MyBB's page header hook for reliable settings-page output.

## 0.6.2 - 2026-09-09

- Fixed Admin CP inventory row editor script output by injecting it through MyBB's printed page header.

## 0.6.1 - 2026-09-09

- Fixed Admin CP inventory row editor injection on the settings display page.

## 0.6.0 - 2026-09-09

- Added optional in-page ad rotation with configurable minimum and maximum timing.

## 0.5.0 - 2026-09-09

- Added independently configurable square and banner advertisement inventories.
- Added per-ad enabled flags with safe URL and text output.
- Added configurable sponsor label and new-tab behavior.
- Added a usergroup exemption setting for hiding ads from selected primary or additional groups.
- Added an Admin CP row editor enhancement for square and banner ad inventories.
- Moved maintained CSS into MyBB-managed theme stylesheets instead of a root `css/` asset.
- Added language files for plugin metadata and Admin CP settings.
- Added release packaging layout, tests, and GitHub/Gitea release workflows.
