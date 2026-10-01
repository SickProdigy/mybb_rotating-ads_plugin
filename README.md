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

Use **Backup & restore** to download all ads as a single JSON backup, upload a backup file, or paste JSON to import it. Imports validate every ad before saving changes and can either append to the current list or replace it.

Delivery weight is relative: ads with the default weight of `1` have equal odds, while an ad with weight `5` is selected five times as often as a weight-`1` ad in the same eligible pool. Start and end dates use UTC and provide the browser's native calendar picker. Limits use `0` for unlimited delivery.

Image and destination fields accept full HTTP(S) URLs or site-relative paths beginning with `/`. For example, an image stored beneath the forum installation can use `/images/sponsored/ad-hostpro.jpg` when the forum is installed at the site root.

Country targeting is configurable. The default automatic order reads country headers supplied by Cloudflare, a server GeoIP module, App Engine, CloudFront, Fastly, or Vercel; then queries an optional local MaxMind-compatible database; and finally uses the first browser language region in `Accept-Language`, such as `en-US`. An allowlist does not deliver when no country signal is available; a blocklist does. The plugin never calls an external geolocation service.

## Settings reference

Rotating Ads settings live in **Admin CP -> Configuration -> Settings -> Rotating Ads**. Individual advertisements are managed separately under **Admin CP -> Configuration -> Rotating Ads**.

| Setting | Purpose | Default |
| --- | --- | --- |
| Template variables | Shows the `{$rotating_ads_square}` and `{$rotating_ads_banner}` variables to place in theme templates. This is a read-only reference. | No value |
| Sponsor label | Text displayed above each ad. Leave blank to hide the label. | `Sponsored` |
| Hide ads from usergroups | Comma-separated primary or additional usergroup IDs that should not see either ad slot. Leave blank to show ads to every group. | Empty |
| Load plugin CSS | Loads the maintained responsive ad stylesheet. Disable it when the active theme supplies all ad styling. | Yes |
| Open ads in a new tab | Adds new-tab behavior to advertisement links. | Yes |
| Show destination in tracked links | Adds a readable `to=example.com` hostname to tracked click URLs so visitors can recognize the destination. | Yes |
| Rotate ads while viewing a page | Uses JavaScript to cycle through eligible ads without a page reload. A slot with fewer than two ads remains static, as does the page for visitors without JavaScript. | No |
| Minimum rotation seconds | Shortest time an ad remains visible during browser-side rotation. | `15` |
| Maximum rotation seconds | Longest rotation delay. A value below the minimum is treated as the minimum. | `30` |
| Country detection | Selects how the plugin resolves the two-letter country code used by ad allowlists and blocklists. See [Country detection options](#country-detection-options). | Automatic |
| MaxMind database path | MyBB-root-relative database file or directory. The recommended `/inc/geoip` directory automatically selects `GeoLite2-Country.mmdb`. Leave blank to skip local lookups. | Empty |
| Visitor IP source | Selects the address used only for local MaxMind lookups. See [Visitor IP source options](#visitor-ip-source-options). | `REMOTE_ADDR` |

### Country detection options

Country targeting controls whether an advertisement is eligible; it does not change the advertisement content. An allowlist does not deliver when no country can be resolved, while a blocklist remains eligible. No mode calls an external geolocation service.

| Option | Behavior |
| --- | --- |
| Headers, then MaxMind, then browser language | Recommended automatic chain. It first checks trusted country headers, then the configured local MaxMind database, and finally a region in `Accept-Language`. |
| Server/proxy headers only | Checks country codes supplied by Cloudflare, a server GeoIP module, App Engine, CloudFront, Fastly, or Vercel. Use when the origin trusts and receives one of those headers. |
| MaxMind database only | Looks up the selected visitor IP in the configured local `.mmdb` database. It requires both the database file and the official `maxmind-db/reader` PHP library. |
| Browser language region only | Uses the first regional browser language, such as `US` from `en-US`. This is a preference signal and may not represent the visitor physical location. |
| Disabled | Resolves no country. Country allowlisted ads do not deliver; blocklisted ads remain eligible because no blocked country was identified. |

### Local MaxMind setup

The free **GeoLite2 Country** database is sufficient for this plugin. Obtain it from the [official MaxMind GeoLite page](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data/):

1. Create or sign in to a free MaxMind account.
2. From the account portal, request access to GeoLite and complete any required enrollment steps. The database downloads appear after MaxMind enables GeoLite access for the account.
3. On **Download files**, find **GeoLite Country** with edition ID `GeoLite2-Country` and format **GeoIP2 Binary (.mmdb)**, then select **Download GZIP**. Do not choose GeoLite ASN or a CSV-format download. GeoLite City also works, but it is larger and provides location detail this plugin does not use. After extracting the download, the database file should be named `GeoLite2-Country.mmdb`. Create `inc/geoip` under the MyBB board root and place the file there, producing `inc/geoip/GeoLite2-Country.mmdb`.
4. Enter `/inc/geoip` in **MaxMind database path**. A path beginning with `/inc` is resolved from the MyBB board root, and a directory path automatically uses the filename `GeoLite2-Country.mmdb`. `/inc/geoip/GeoLite2-Country.mmdb` is also accepted.
5. For automated downloads, generate a MaxMind license key and configure `geoipupdate`. The license key authenticates database downloads; the plugin does not use it and does not call a MaxMind lookup API.
6. Keep the database current. MaxMind requires GeoLite users to replace old data after new releases.

Rotating Ads includes MaxMind official pure-PHP reader, so no Composer command, PHP extension, API service, or reader-path setting is required. The bundled reader requires PHP 7.2 or newer. If a forum-root Composer installation already provides the reader, it is detected before the bundled copy. A paid GeoIP2 Country or City `.mmdb` file can be used the same way.

The plugin performs only local database reads and sends no visitor address to MaxMind. If the reader, autoloader, database, IP address, or lookup is unavailable, the lookup returns no country instead of interrupting the forum page. Use **Check MaxMind setup** beside the database-path setting, or open the **MaxMind status** tab under **Configuration -> Rotating Ads**, to verify the resolved filename, file readability, PHP reader, and database format.

### Visitor IP source options

This setting supplies the address used only for the optional local MaxMind lookup. It does not affect country codes supplied directly by a CDN, proxy, hosting platform, or server GeoIP module. Most administrators should keep `REMOTE_ADDR`.

| Option | When to use it |
| --- | --- |
| `REMOTE_ADDR (recommended)` | Use for a directly hosted forum and whenever you are unsure. It reads the address connected to the web server and cannot be replaced by a visitor-supplied HTTP header. Behind a proxy, it may identify the proxy instead of the visitor. |
| `CF-Connecting-IP (Cloudflare only)` | Use when the origin accepts traffic through Cloudflare and Cloudflare overwrites this header with the visitor address. Restrict origin access to Cloudflare so visitors cannot bypass it and supply their own value. |
| `X-Forwarded-For (trusted proxy only)` | Use only when a trusted reverse proxy or load balancer overwrites or sanitizes this header. The plugin uses the first valid address in its comma-separated list. Do not use it when visitors can connect directly to the origin and supply the header themselves. |
| `X-Real-IP (trusted proxy only)` | Use when a trusted reverse proxy, commonly Nginx, deliberately overwrites this header with the visitor address. Do not select it merely because the header is present. |

Forwarded IP headers are not trustworthy by themselves. If a visitor can supply or preserve the selected header, they can influence country targeting. Keep `REMOTE_ADDR` unless the forum is behind a proxy you control or a trusted service whose connection to the origin is enforced.

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

The manager displays lifetime image impressions, tracked clicks, and CTR for each ad. Image and destination requests pass through lightweight `misc.php` redirects so static, non-JavaScript views are included. Click URLs use the concise `ra_click` action and can include a readable `to` hostname so visitors can see the destination domain before following the link. The server still resolves the destination from the ad ID, preventing URL tampering. Hidden rotating images are loaded only when shown. Metrics are aggregate operational totals: no visitor identity is stored, and the counts are not intended as billing-grade bot-filtered analytics.

## Uninstall

Uninstalling removes the Rotating Ads settings. MyBB deactivates the plugin first, removing its maintained stylesheet.

## License

Copyright (C) 2026 SickProdigy. Licensed under [GPL-3.0-only](LICENSE).
