# MyBB Rotating Ads Plugin

This plugin owns Sick Gaming's rotating promotion/ad behavior for MyBB.

## Objective

Move promotion-specific code out of `sg-website-theme` and into this plugin so the theme stays focused on layout, templates, and styling.

In particular, `sg-promotions.js` should live with this plugin if it controls promotion slots, ad rotation, ad rendering, or related frontend behavior.

## Repo Boundary

This plugin should own:

- rotating ad/promotion logic;
- `sg-promotions.js` or equivalent frontend assets;
- plugin settings for ad slots, rotation timing, enabled/disabled state, and debug behavior;
- backend/admin behavior needed to manage promotions;
- documentation for installing and configuring the plugin in MyBB.

The theme repo should own only:

- visual placement of promotion areas;
- CSS for how promotion slots look;
- minimal template hooks/placeholders consumed by this plugin.

Example theme-side placeholder:

```html
<div data-sg-promotion-slot="header"></div>
```

## Planned Migration

1. Confirm whether `sg-promotions.js` is currently required by the live SickGaming.net theme.
2. Add `sg-promotions.js` to this plugin's asset structure.
3. Update the plugin to load the script on pages that need promotion slots.
4. Leave stable template placeholders in `sg-website-theme`.
5. Remove duplicate promotion JS from `sg-website-theme` after the plugin is confirmed to load it.

## Install Notes

Exact install paths should follow MyBB plugin conventions used by this project. Until the plugin structure is finalized, avoid storing live credentials, private config, or production-only values in this repo.
