# MyBB Rotating Ads Plugin

Standalone square and banner advertisement rotation for MyBB 1.8.

## Features

- Independent square and banner ad inventories.
- Random server-side selection on each page request.
- Per-ad enabled flags.
- Plugin-owned responsive styling.
- Safe image, link, and alternative-text output.
- Empty output when a slot has no enabled ads.

## Install

Copy the contents of `Upload/` into the MyBB installation root:

```text
Upload/inc/plugins/rotating_ads.php -> public_html/inc/plugins/rotating_ads.php
Upload/css/rotating-ads.css -> public_html/css/rotating-ads.css
```

Then install and activate `Rotating Ads` under Admin CP → Configuration → Plugins.

Activation inserts `{$rotating_ads_assets}` after `{$stylesheets}` in `headerinclude`. Deactivation removes it.

## Template Variables

Place either variable anywhere MyBB evaluates a template:

```html
{$rotating_ads_square}
{$rotating_ads_banner}
```

MyBB/PHP variable names cannot contain hyphens, so the supported variables use underscores rather than names such as `{$rotating-ads-square}`.

The plugin does not impose either slot on a theme. Administrators place `{$rotating_ads_square}` and `{$rotating_ads_banner}` wherever each format belongs.

## Configuration

The plugin creates separate `Square Ads` and `Banner Ads` settings. Enter one ad per line:

```text
image URL|destination URL|alt text|enabled
```

Example:

```text
https://example.com/ad-square.jpg|https://example.com/|Example sponsor|1
```

Set enabled to `1` or `0`. Blank lines and lines beginning with `#` are ignored. The initial square inventory contains the former hard-coded HostPro advertisement. The banner inventory starts empty.

## Output

Each selected advertisement uses plugin-owned markup and styling:

```html
<aside class="rotating-ad rotating-ad--square">
    <div class="rotating-ad__title">Sponsored</div>
    <a class="rotating-ad__link" rel="sponsored noopener">
        <img class="rotating-ad__image" />
    </a>
</aside>
```

## Planned VIP Gold Preference

Gitea issue #1 tracks a User CP preference allowing eligible VIP Gold members to hide both ad formats. That preference is not implemented in version 1.0.0.

## Manual Asset Integration

If a customized `headerinclude` lacks `{$stylesheets}`, add this variable manually:

```html
{$rotating_ads_assets}
```

## Uninstall

Uninstalling removes the Rotating Ads settings. MyBB deactivates the plugin first, removing its asset insertion.
