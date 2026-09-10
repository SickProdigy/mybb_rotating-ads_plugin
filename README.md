# MyBB Rotating Ads Plugin

Standalone square and banner advertisement rotation for MyBB 1.8.

## Features

- Independent square and banner ad inventories.
- Random server-side selection on each page request.
- Optional in-page rotation with configurable timing.
- Per-ad schedules, delivery weights, view/click limits, and enabled flags.
- Per-ad impression, click, and click-through-rate totals.
- Country allowlists and blocklists when a country header is available.
- Theme-conscious responsive styling that can be disabled.
- Configurable sponsor label and new-tab behavior.
- Safe image, link, and alternative-text output.
- Empty output when a slot has no enabled ads.

## Install

Copy the contents of `Upload/` into the MyBB installation root:

```text
Upload/inc/plugins/rotating_ads.php -> public_html/inc/plugins/rotating_ads.php
Upload/inc/languages/english/rotating_ads.lang.php -> public_html/inc/languages/english/rotating_ads.lang.php
Upload/inc/languages/english/admin/rotating_ads.lang.php -> public_html/inc/languages/english/admin/rotating_ads.lang.php
Upload/jscripts/rotating-ads.js -> public_html/jscripts/rotating-ads.js
```

Then install and activate `Rotating Ads` under Admin CP → Configuration → Plugins.

Activation adds the maintained plugin stylesheet to each theme through MyBB's stylesheet manager. Deactivation removes it.

## Template Variables

Place either variable anywhere MyBB evaluates a template:

```html
{$rotating_ads_square}
{$rotating_ads_banner}
```

MyBB/PHP variable names cannot contain hyphens, so the supported variables use underscores rather than names such as `{$rotating-ads-square}`.

The plugin does not impose either slot on a theme. Administrators place `{$rotating_ads_square}` and `{$rotating_ads_banner}` wherever each format belongs.

## Configuration

Manage individual ads in **Admin CP -> Configuration -> Rotating Ads**. Use **Add ad** to create a square or banner ad, then configure its weight, schedule, limits, country targeting, or status from the native MyBB management page.

Delivery weight is relative: ads with the default weight of `1` have equal odds, while an ad with weight `5` is selected five times as often as a weight-`1` ad in the same eligible pool. Start and end dates use UTC. Limits use `0` for unlimited delivery.

Image and destination fields accept full HTTP(S) URLs or site-relative paths beginning with `/`. For example, an image stored beneath the forum installation can use `/images/sponsored/ad-hostpro.jpg` when the forum is installed at the site root.

Country targeting reads the first valid country code supplied by `CF-IPCountry`, `GEOIP_COUNTRY_CODE`, or `X-AppEngine-Country`. An allowlist does not deliver when no country code is available; a blocklist does. The plugin does not call an external geolocation service.

General display and timing options remain under **Configuration -> Settings -> Rotating Ads**.
That page also displays the two supported template variables as read-only values; the plugin never inserts an ad slot into the index or another template automatically.

Additional settings:

- `Sponsor label`: Optional text displayed above each ad. Leave blank to hide it.
- `Hide ads from usergroups`: Comma-separated primary or additional usergroup IDs that should not see ads.
- `Load plugin CSS`: Disable if your theme provides its own ad styling.
- `Open ads in a new tab`: Controls whether links include `target="_blank"`.
- `Rotate ads while viewing a page`: Enables JavaScript-powered browser-side cycling when a slot has at least two enabled ads.
- `Minimum rotation seconds` / `Maximum rotation seconds`: Controls the random delay range between ad changes. If maximum is below minimum, the plugin treats it as the minimum.

## Output

Each selected advertisement uses plugin-owned markup and styling:

```html
<aside class="rotating-ad rotating-ad--square">
    <div class="rotating-ad__title">Sponsored</div>
    <a class="rotating-ad__link" rel="sponsored noopener noreferrer">
        <img class="rotating-ad__image" />
    </a>
</aside>
```

## Release Packaging

Tagged releases package `Upload`, `README.md`, `LICENSE`, and `CHANGELOG.md` into a `mybb-rotating-ads-{version}.zip` archive.

Local checks:

```bash
php -l Upload/inc/plugins/rotating_ads.php
php tests/rotating_ads_test.php
```

## Usergroup Exemptions

To hide both ad formats from VIP Gold or any other group, enter that group's ID in `Hide ads from usergroups`. Primary and additional usergroups are checked.

## In-Page Rotation

By default, the plugin chooses one random ad for each slot on each page request. Enable `Rotate ads while viewing a page` to let JavaScript cycle through all enabled ads in a populated slot. Visitors without JavaScript still see the initially rendered ad.

## Metrics

The manager displays lifetime image impressions, tracked clicks, and CTR for each ad. Image and destination requests pass through lightweight `misc.php` redirects so static, non-JavaScript views are included. Click URLs use the concise `ra_click` action and include a readable `to` hostname so visitors can see the destination domain before following the link. The server still resolves the destination from the ad ID, preventing URL tampering. Hidden rotating images are loaded only when shown. Metrics are aggregate operational totals: no visitor identity is stored, and the counts are not intended as billing-grade bot-filtered analytics.

## Uninstall

Uninstalling removes the Rotating Ads settings. MyBB deactivates the plugin first, removing its maintained stylesheet.

## License

Copyright (C) 2026 SickProdigy. Licensed under [GPL-3.0-only](LICENSE).
