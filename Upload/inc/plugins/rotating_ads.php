<?php
/**
 * Rotating Ads
 *
 * Configurable square and banner advertisement slots for MyBB.
 * Copyright (C) 2026 SickProdigy
 * SPDX-License-Identifier: GPL-3.0-only
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

function rotating_ads_info()
{
    global $lang;

    if (isset($lang)) {
        $lang->load('rotating_ads');
    }

    return array(
        'name' => isset($lang->rotating_ads_name) ? $lang->rotating_ads_name : 'Rotating Ads',
        'description' => isset($lang->rotating_ads_description) ? $lang->rotating_ads_description : 'Provides independently configurable square and banner advertisement slots.',
        'website' => 'https://www.sickgaming.net',
        'author' => 'SickProdigy',
        'authorsite' => 'https://www.sickgaming.net',
        'version' => '0.6.3',
        'compatibility' => '18*',
        'license' => 'GPL-3.0-only'
    );
}

function rotating_ads_install()
{
    rotating_ads_ensure_settings();
}

function rotating_ads_is_installed()
{
    global $db;

    $query = $db->simple_select('settinggroups', 'gid', "name='rotating_ads'");
    $group = $db->fetch_array($query);

    return !empty($group['gid']);
}

function rotating_ads_uninstall()
{
    global $db;

    rotating_ads_remove_stylesheets();

    $query = $db->simple_select('settinggroups', 'gid', "name='rotating_ads'");
    $group = $db->fetch_array($query);

    if (!empty($group['gid'])) {
        $gid = (int)$group['gid'];
        $db->delete_query('settings', "gid='{$gid}'");
        $db->delete_query('settinggroups', "gid='{$gid}'");
        rotating_ads_rebuild_settings();
    }
}

function rotating_ads_activate()
{
    rotating_ads_ensure_settings();
    rotating_ads_refresh_stylesheets();

    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets(
        'headerinclude',
        '#' . preg_quote('{$rotating_ads_assets}') . '#i',
        ''
    );
    find_replace_templatesets(
        'headerinclude',
        '#' . preg_quote('{$stylesheets}') . '#i',
        '{$stylesheets}{$rotating_ads_assets}'
    );
}

function rotating_ads_deactivate()
{
    rotating_ads_remove_stylesheets();

    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets(
        'headerinclude',
        '#' . preg_quote('{$rotating_ads_assets}') . '#i',
        ''
    );
}

function rotating_ads_ensure_settings()
{
    global $db, $lang;

    if (isset($lang)) {
        $lang->load('rotating_ads');
    }

    $query = $db->simple_select('settinggroups', 'gid', "name='rotating_ads'");
    $group = $db->fetch_array($query);

    if (!empty($group['gid'])) {
        $gid = (int)$group['gid'];
        $db->update_query('settinggroups', array(
            'title' => rotating_ads_lang('rotating_ads_name', 'Rotating Ads'),
            'description' => rotating_ads_lang('rotating_ads_settings_description', 'Settings for square and banner advertisement inventories.'),
            'disporder' => 1,
            'isdefault' => 0
        ), "gid='{$gid}'");
    } else {
        $gid = (int)$db->insert_query('settinggroups', array(
            'name' => 'rotating_ads',
            'title' => rotating_ads_lang('rotating_ads_name', 'Rotating Ads'),
            'description' => rotating_ads_lang('rotating_ads_settings_description', 'Settings for square and banner advertisement inventories.'),
            'disporder' => 1,
            'isdefault' => 0
        ));
    }

    $settings = array(
        array(
            'name' => 'rotating_ads_square_inventory',
            'title' => rotating_ads_lang('rotating_ads_square_inventory', 'Square Ads'),
            'description' => rotating_ads_lang('rotating_ads_inventory_description', 'One ad per line: image URL|destination URL|alt text|enabled.'),
            'optionscode' => 'textarea',
            'value' => '',
            'disporder' => 1,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_banner_inventory',
            'title' => rotating_ads_lang('rotating_ads_banner_inventory', 'Banner Ads'),
            'description' => rotating_ads_lang('rotating_ads_inventory_description', 'One ad per line: image URL|destination URL|alt text|enabled.'),
            'optionscode' => 'textarea',
            'value' => '',
            'disporder' => 2,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_sponsor_label',
            'title' => rotating_ads_lang('rotating_ads_sponsor_label', 'Sponsor label'),
            'description' => rotating_ads_lang('rotating_ads_sponsor_label_description', 'Optional label displayed above each ad. Leave blank to hide the label.'),
            'optionscode' => 'text',
            'value' => rotating_ads_lang('rotating_ads_default_sponsor_label', 'Sponsored'),
            'disporder' => 3,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_hidden_groups',
            'title' => rotating_ads_lang('rotating_ads_hidden_groups', 'Hide ads from usergroups'),
            'description' => rotating_ads_lang('rotating_ads_hidden_groups_description', 'Comma-separated primary or additional usergroup IDs that should not see rotating ads. Leave blank to show ads to all groups.'),
            'optionscode' => 'text',
            'value' => '',
            'disporder' => 4,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_enable_css',
            'title' => rotating_ads_lang('rotating_ads_enable_css', 'Load plugin CSS'),
            'description' => rotating_ads_lang('rotating_ads_enable_css_description', 'Load the small default stylesheet. Disable this if your theme provides its own ad styling.'),
            'optionscode' => 'yesno',
            'value' => '1',
            'disporder' => 5,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_open_new_tab',
            'title' => rotating_ads_lang('rotating_ads_open_new_tab', 'Open ads in a new tab'),
            'description' => rotating_ads_lang('rotating_ads_open_new_tab_description', 'Open advertisement links in a new browser tab.'),
            'optionscode' => 'yesno',
            'value' => '1',
            'disporder' => 6,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_enable_rotation',
            'title' => rotating_ads_lang('rotating_ads_enable_rotation', 'Rotate ads while viewing a page'),
            'description' => rotating_ads_lang('rotating_ads_enable_rotation_description', 'Cycle through enabled ads without requiring a page reload. Requires JavaScript; visitors without JavaScript keep the normal static output. Slots with fewer than two enabled ads also stay static.'),
            'optionscode' => 'yesno',
            'value' => '0',
            'disporder' => 7,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_rotation_min_seconds',
            'title' => rotating_ads_lang('rotating_ads_rotation_min_seconds', 'Minimum rotation seconds'),
            'description' => rotating_ads_lang('rotating_ads_rotation_min_seconds_description', 'Minimum seconds an ad remains visible before the next rotation.'),
            'optionscode' => 'numeric',
            'value' => '15',
            'disporder' => 8,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_rotation_max_seconds',
            'title' => rotating_ads_lang('rotating_ads_rotation_max_seconds', 'Maximum rotation seconds'),
            'description' => rotating_ads_lang('rotating_ads_rotation_max_seconds_description', 'Maximum seconds an ad remains visible before the next rotation. Values below the minimum are treated as the minimum.'),
            'optionscode' => 'numeric',
            'value' => '30',
            'disporder' => 9,
            'gid' => $gid
        )
    );

    foreach ($settings as $setting) {
        $query = $db->simple_select('settings', 'sid', "name='" . $db->escape_string($setting['name']) . "'");
        $existing = $db->fetch_array($query);

        if (empty($existing['sid'])) {
            $db->insert_query('settings', $setting);
        } else {
            unset($setting['value']);
            $db->update_query('settings', $setting, "sid='" . (int)$existing['sid'] . "'");
        }
    }

    rotating_ads_rebuild_settings();
}

$plugins->add_hook('global_start', 'rotating_ads_build_output');
function rotating_ads_build_output()
{
    global $mybb, $rotating_ads_assets, $rotating_ads_square, $rotating_ads_banner;

    $rotating_ads_assets = '';
    $rotating_ads_square = '';
    $rotating_ads_banner = '';

    if (rotating_ads_current_user_hidden()) {
        return;
    }

    $square_ads = rotating_ads_parse_inventory(isset($mybb->settings['rotating_ads_square_inventory'])
        ? $mybb->settings['rotating_ads_square_inventory']
        : '');
    $banner_ads = rotating_ads_parse_inventory(isset($mybb->settings['rotating_ads_banner_inventory'])
        ? $mybb->settings['rotating_ads_banner_inventory']
        : '');
    $label = isset($mybb->settings['rotating_ads_sponsor_label'])
        ? trim((string)$mybb->settings['rotating_ads_sponsor_label'])
        : rotating_ads_lang('rotating_ads_default_sponsor_label', 'Sponsored');
    $open_new_tab = rotating_ads_setting_enabled('rotating_ads_open_new_tab', true);
    $rotation_options = rotating_ads_rotation_options();

    if ($rotation_options['enabled'] && (count($square_ads) > 1 || count($banner_ads) > 1)) {
        $asset_base = isset($mybb->asset_url) && $mybb->asset_url !== '' ? $mybb->asset_url : $mybb->settings['bburl'];
        $asset_url = rtrim($asset_base, '/');
        $script_url = $asset_url . '/jscripts/rotating-ads.js?ver=060';
        $rotating_ads_assets = '<script type="text/javascript" src="' . htmlspecialchars_uni($script_url) . '" defer="defer"></script>';
    }

    $rotating_ads_square = rotating_ads_render_slot('square', $square_ads, $label, $open_new_tab, $rotation_options);
    $rotating_ads_banner = rotating_ads_render_slot('banner', $banner_ads, $label, $open_new_tab, $rotation_options);
}

function rotating_ads_parse_inventory($value)
{
    $ads = array();
    $lines = preg_split('/\r\n|\r|\n/', trim((string)$value));

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        $parts = array_map('trim', explode('|', $line, 4));
        $image_url = isset($parts[0]) ? $parts[0] : '';
        $destination_url = isset($parts[1]) ? $parts[1] : '';
        $alt_text = isset($parts[2]) ? $parts[2] : '';
        $enabled = !isset($parts[3]) || (int)$parts[3] === 1;

        if (!preg_match('#^https?://#i', $image_url) || !preg_match('#^https?://#i', $destination_url) || !$enabled) {
            continue;
        }

        $ads[] = array(
            'image_url' => $image_url,
            'destination_url' => $destination_url,
            'alt_text' => $alt_text
        );
    }

    return $ads;
}

function rotating_ads_render_slot($format, $ads, $label = 'Sponsored', $open_new_tab = true, $rotation_options = array())
{
    if (empty($ads)) {
        return '';
    }

    $format = $format === 'banner' ? 'banner' : 'square';
    $title = trim((string)$label) !== ''
        ? '<div class="rotating-ad__title">' . htmlspecialchars_uni($label) . '</div>'
        : '';
    $rotate = !empty($rotation_options['enabled']) && count($ads) > 1;

    if ($rotate) {
        $first_index = array_rand($ads);
        $first_ad = $ads[$first_index];
        unset($ads[$first_index]);
        array_unshift($ads, $first_ad);

        $min = isset($rotation_options['min_seconds']) ? (int)$rotation_options['min_seconds'] : 15;
        $max = isset($rotation_options['max_seconds']) ? (int)$rotation_options['max_seconds'] : $min;
        $links = '';

        foreach (array_values($ads) as $index => $ad) {
            $links .= rotating_ads_render_link($ad, $open_new_tab, $index > 0);
        }

        return '<aside class="rotating-ad rotating-ad--' . $format . '" data-rotating-ads="1" data-rotating-ads-min="' . (int)$min . '" data-rotating-ads-max="' . (int)$max . '">'
            . $title
            . $links
            . '</aside>';
    }

    $ad = $ads[array_rand($ads)];

    return '<aside class="rotating-ad rotating-ad--' . $format . '">'
        . $title
        . rotating_ads_render_link($ad, $open_new_tab)
        . '</aside>';
}

function rotating_ads_render_link($ad, $open_new_tab = true, $hidden = false)
{
    $target = $open_new_tab ? ' target="_blank"' : '';
    $rel = $open_new_tab ? 'sponsored noopener noreferrer' : 'sponsored';
    $hidden_attribute = $hidden ? ' hidden="hidden"' : '';

    return '<a class="rotating-ad__link" href="' . htmlspecialchars_uni($ad['destination_url']) . '"' . $target . ' rel="' . $rel . '"' . $hidden_attribute . '>'
        . '<img class="rotating-ad__image" src="' . htmlspecialchars_uni($ad['image_url']) . '" alt="' . htmlspecialchars_uni($ad['alt_text']) . '" loading="lazy" />'
        . '</a>';
}

function rotating_ads_setting_enabled($name, $default = true)
{
    global $mybb;

    if (!isset($mybb->settings[$name])) {
        return (bool)$default;
    }

    return (string)$mybb->settings[$name] !== '0';
}

function rotating_ads_rotation_options()
{
    global $mybb;

    $min = isset($mybb->settings['rotating_ads_rotation_min_seconds'])
        ? (int)$mybb->settings['rotating_ads_rotation_min_seconds']
        : 15;
    $max = isset($mybb->settings['rotating_ads_rotation_max_seconds'])
        ? (int)$mybb->settings['rotating_ads_rotation_max_seconds']
        : 30;

    $min = max(1, $min);
    $max = max(1, $max);

    if ($max < $min) {
        $max = $min;
    }

    return array(
        'enabled' => rotating_ads_setting_enabled('rotating_ads_enable_rotation', false),
        'min_seconds' => $min,
        'max_seconds' => $max
    );
}

function rotating_ads_stylesheet()
{
    return <<<'CSS'
.rotating-ad {
    margin: 0 0 20px;
    overflow: hidden;
    border: 1px solid rgba(127, 127, 127, 0.35);
    background: transparent;
}

.rotating-ad__title {
    padding: 6px 8px;
    border-bottom: 1px solid rgba(127, 127, 127, 0.25);
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.rotating-ad__link,
.rotating-ad__image {
    display: block;
}

.rotating-ad__link[hidden] {
    display: none;
}

.rotating-ad__image {
    width: 100%;
    height: auto;
}

.rotating-ad--square {
    max-width: 320px;
}

.rotating-ad--banner {
    width: 100%;
}
CSS;
}

function rotating_ads_load_theme_functions()
{
    global $config;

    if (function_exists('cache_stylesheet') && function_exists('update_theme_stylesheet_list')) {
        return true;
    }

    $candidates = array();

    if (!empty($_SERVER['SCRIPT_FILENAME'])) {
        $candidates[] = dirname($_SERVER['SCRIPT_FILENAME']) . '/inc/functions_themes.php';
    }

    if (!empty($config['admin_dir'])) {
        $candidates[] = MYBB_ROOT . trim($config['admin_dir'], '/\\') . '/inc/functions_themes.php';
    }

    $candidates[] = MYBB_ROOT . 'admin/inc/functions_themes.php';

    foreach (array_unique($candidates) as $functions_file) {
        if (file_exists($functions_file)) {
            require_once $functions_file;
            break;
        }
    }

    return function_exists('cache_stylesheet') && function_exists('update_theme_stylesheet_list');
}

function rotating_ads_refresh_stylesheets()
{
    if (rotating_ads_setting_enabled('rotating_ads_enable_css', true)) {
        return rotating_ads_sync_stylesheets();
    }

    return rotating_ads_remove_stylesheets();
}

function rotating_ads_sync_stylesheets()
{
    global $db;

    if (!rotating_ads_load_theme_functions()) {
        return false;
    }

    $name = 'rotating_ads_plugin.css';
    $stylesheet = rotating_ads_stylesheet();
    $theme_ids = array();
    $query = $db->simple_select('themes', 'tid', 'tid > 1');

    while ($theme = $db->fetch_array($query)) {
        $tid = (int)$theme['tid'];
        $theme_ids[$tid] = $tid;
        $existing_query = $db->simple_select(
            'themestylesheets',
            'sid',
            "tid='{$tid}' AND name='" . $db->escape_string($name) . "'",
            array('limit' => 1)
        );
        $existing = $db->fetch_array($existing_query);
        $stylesheet_data = array(
            'name' => $db->escape_string($name),
            'tid' => $tid,
            'attachedto' => '',
            'stylesheet' => $db->escape_string($stylesheet),
            'cachefile' => $db->escape_string($name),
            'lastmodified' => TIME_NOW
        );

        if (!empty($existing['sid'])) {
            $sid = (int)$existing['sid'];
            $db->update_query('themestylesheets', $stylesheet_data, "sid='{$sid}'", 1);
        } else {
            $sid = (int)$db->insert_query('themestylesheets', $stylesheet_data);
        }

        if (!cache_stylesheet($tid, $name, $stylesheet)) {
            $db->update_query(
                'themestylesheets',
                array('cachefile' => "css.php?stylesheet={$sid}"),
                "sid='{$sid}'",
                1
            );
        }
    }

    foreach ($theme_ids as $tid) {
        update_theme_stylesheet_list($tid);
    }

    return true;
}

function rotating_ads_remove_stylesheets()
{
    global $db;

    if (!rotating_ads_load_theme_functions()) {
        return false;
    }

    $name = 'rotating_ads_plugin.css';
    $theme_ids = array();
    $query = $db->simple_select(
        'themestylesheets',
        'sid, tid, cachefile',
        "name='" . $db->escape_string($name) . "'"
    );

    while ($stylesheet = $db->fetch_array($query)) {
        $sid = (int)$stylesheet['sid'];
        $tid = (int)$stylesheet['tid'];
        $cachefile = basename($stylesheet['cachefile']);
        $theme_ids[$tid] = $tid;
        $db->delete_query('themestylesheets', "sid='{$sid}'", 1);

        if ($cachefile !== '' && strpos($cachefile, 'css.php') === false) {
            @unlink(MYBB_ROOT . "cache/themes/theme{$tid}/{$cachefile}");
            @unlink(MYBB_ROOT . 'cache/themes/theme' . $tid . '/' . str_replace('.css', '.min.css', $cachefile));
            @unlink(MYBB_ROOT . "cache/themes/{$tid}_{$cachefile}");
            @unlink(MYBB_ROOT . 'cache/themes/' . $tid . '_' . str_replace('.css', '.min.css', $cachefile));
        }
    }

    foreach ($theme_ids as $tid) {
        update_theme_stylesheet_list($tid);
    }

    return true;
}

if (defined('IN_ADMINCP')) {
    $plugins->add_hook('admin_load', 'rotating_ads_admin_settings_editor');
    $plugins->add_hook('admin_page_output_header', 'rotating_ads_admin_settings_editor');
    $plugins->add_hook('admin_config_settings_start', 'rotating_ads_admin_settings_editor');
    $plugins->add_hook('admin_style_themes_add_commit', 'rotating_ads_refresh_stylesheets');
    $plugins->add_hook('admin_style_themes_import_commit', 'rotating_ads_refresh_stylesheets');
    $plugins->add_hook('admin_style_themes_duplicate_commit', 'rotating_ads_refresh_stylesheets');
    $plugins->add_hook('admin_config_settings_change', 'rotating_ads_refresh_stylesheets');
    $plugins->add_hook('admin_config_settings_change', 'rotating_ads_admin_settings_editor');
}

function rotating_ads_admin_settings_editor()
{
    global $mybb, $page, $db;

    static $injected = false;

    if ($injected) {
        return;
    }

    if (!isset($page) || !isset($db)) {
        return;
    }

    $query = $db->simple_select('settinggroups', 'gid', "name='rotating_ads'", array('limit' => 1));
    $group = $db->fetch_array($query);

    if (empty($group['gid']) || (int)$mybb->get_input('gid') !== (int)$group['gid']) {
        return;
    }

    $injected = true;

    $strings = rotating_ads_admin_editor_strings();
    $strings_json = json_encode($strings);
    if ($strings_json === false) {
        $strings_json = '{}';
    }
    $strings_json = str_replace(array('<', '>', '&'), array('\u003C', '\u003E', '\u0026'), $strings_json);

    $page->extra_header .= '<style type="text/css">
.rotating-ads-editor {
    max-width: 980px;
    margin-top: 8px;
}

.rotating-ads-editor__table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #ccc;
    background: #fff;
}

.rotating-ads-editor__table th,
.rotating-ads-editor__table td {
    padding: 7px;
    border: 1px solid #ddd;
    vertical-align: top;
}

.rotating-ads-editor__table th {
    background: #f5f5f5;
    text-align: left;
}

.rotating-ads-editor__table input[type="text"] {
    box-sizing: border-box;
    width: 100%;
}

.rotating-ads-editor__enabled {
    text-align: center;
    white-space: nowrap;
}

.rotating-ads-editor__actions {
    margin-top: 8px;
}

.rotating-ads-editor__fallback {
    margin-top: 8px;
}

.rotating-ads-editor--enhanced .rotating-ads-editor__fallback {
    display: none;
}
</style>';

    $page->extra_header .= '<script type="text/javascript">
window.rotatingAdsEditorLanguage = ' . $strings_json . ';
(function(w, d) {
    "use strict";

    var language = w.rotatingAdsEditorLanguage || {};
    var fields = [
        {name: "rotating_ads_square_inventory", label: language.squareAds || "Square Ads"},
        {name: "rotating_ads_banner_inventory", label: language.bannerAds || "Banner Ads"}
    ];

    function text(key, fallback) {
        return language[key] || fallback;
    }

    function escapeSelector(value) {
        if (w.CSS && typeof w.CSS.escape === "function") {
            return w.CSS.escape(value);
        }

        return value.replace(/"/g, "\\\"");
    }

    function findField(name) {
        return d.querySelector("textarea[name=\"upsetting[" + escapeSelector(name) + "]\"]");
    }

    function findInput(name) {
        return d.querySelector("input[name=\"upsetting[" + escapeSelector(name) + "]\"]");
    }

    function parseRows(value) {
        var rows = [];
        var lines = String(value || "").split(/\r\n|\r|\n/);

        lines.forEach(function(line) {
            line = line.trim();
            if (!line || line.charAt(0) === "#") {
                return;
            }

            var parts = line.split("|");
            rows.push({
                image: (parts.shift() || "").trim(),
                url: (parts.shift() || "").trim(),
                alt: parts.length > 1 ? parts.slice(0, -1).join("|").trim() : (parts.shift() || "").trim(),
                enabled: parts.length ? parts.pop().trim() !== "0" : true
            });
        });

        return rows;
    }

    function serializeRows(editor) {
        var lines = [];
        editor.querySelectorAll("tbody tr").forEach(function(row) {
            var image = row.querySelector("[data-ad-field=\"image\"]").value.trim();
            var url = row.querySelector("[data-ad-field=\"url\"]").value.trim();
            var alt = row.querySelector("[data-ad-field=\"alt\"]").value.trim().replace(/\|/g, "/");
            var enabled = row.querySelector("[data-ad-field=\"enabled\"]").checked ? "1" : "0";

            if (image || url || alt) {
                lines.push([image, url, alt, enabled].join("|"));
            }
        });

        return lines.join("\n");
    }

    function makeInput(field, value) {
        var input = d.createElement("input");
        input.type = "text";
        input.value = value || "";
        input.setAttribute("data-ad-field", field);
        return input;
    }

    function addRow(editor, data) {
        var tbody = editor.querySelector("tbody");
        var row = d.createElement("tr");
        data = data || {};

        ["image", "url", "alt"].forEach(function(field) {
            var cell = d.createElement("td");
            cell.appendChild(makeInput(field, data[field]));
            row.appendChild(cell);
        });

        var enabledCell = d.createElement("td");
        enabledCell.className = "rotating-ads-editor__enabled";
        var enabled = d.createElement("input");
        enabled.type = "checkbox";
        enabled.checked = data.enabled !== false;
        enabled.setAttribute("data-ad-field", "enabled");
        enabledCell.appendChild(enabled);
        row.appendChild(enabledCell);

        var actionCell = d.createElement("td");
        var remove = d.createElement("button");
        remove.type = "button";
        remove.className = "button";
        remove.textContent = text("remove", "Remove");
        remove.addEventListener("click", function() {
            row.parentNode.removeChild(row);
        });
        actionCell.appendChild(remove);
        row.appendChild(actionCell);

        tbody.appendChild(row);
    }

    function buildEditor(textarea, config) {
        var wrapper = d.createElement("div");
        wrapper.className = "rotating-ads-editor rotating-ads-editor--enhanced";
        wrapper.innerHTML =
            "<table class=\"rotating-ads-editor__table\">" +
            "<thead><tr>" +
            "<th>" + text("imageUrl", "Image URL") + "</th>" +
            "<th>" + text("destinationUrl", "Destination URL") + "</th>" +
            "<th>" + text("altText", "Alt text") + "</th>" +
            "<th>" + text("enabled", "Enabled") + "</th>" +
            "<th>" + text("actions", "Actions") + "</th>" +
            "</tr></thead><tbody></tbody></table>" +
            "<div class=\"rotating-ads-editor__actions\"></div>" +
            "<div class=\"rotating-ads-editor__fallback\"></div>";

        var actions = wrapper.querySelector(".rotating-ads-editor__actions");
        var add = d.createElement("button");
        add.type = "button";
        add.className = "button";
        add.textContent = text("addAd", "Add ad");
        add.addEventListener("click", function() {
            addRow(wrapper, {});
        });
        actions.appendChild(add);

        parseRows(textarea.value).forEach(function(row) {
            addRow(wrapper, row);
        });

        if (!wrapper.querySelector("tbody tr")) {
            addRow(wrapper, {});
        }

        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.querySelector(".rotating-ads-editor__fallback").appendChild(textarea);

        var form = textarea.form;
        if (form) {
            form.addEventListener("submit", function() {
                textarea.value = serializeRows(wrapper);
            });
        }
    }

    function setupTimingValidation() {
        var min = findInput("rotating_ads_rotation_min_seconds");
        var max = findInput("rotating_ads_rotation_max_seconds");

        if (!min || !max) {
            return;
        }

        function normalize() {
            var minValue = Math.max(1, parseInt(min.value, 10) || 1);
            var maxValue = Math.max(1, parseInt(max.value, 10) || minValue);

            if (maxValue < minValue) {
                maxValue = minValue;
            }

            min.value = minValue;
            max.value = maxValue;
        }

        min.addEventListener("change", normalize);
        max.addEventListener("change", normalize);

        if (min.form) {
            min.form.addEventListener("submit", normalize);
        }
    }

    d.addEventListener("DOMContentLoaded", function() {
        fields.forEach(function(config) {
            var textarea = findField(config.name);
            if (textarea) {
                buildEditor(textarea, config);
            }
        });
        setupTimingValidation();
    });
}(window, document));
</script>';
}

function rotating_ads_admin_editor_strings()
{
    return array(
        'squareAds' => rotating_ads_lang('rotating_ads_square_inventory', 'Square Ads'),
        'bannerAds' => rotating_ads_lang('rotating_ads_banner_inventory', 'Banner Ads'),
        'imageUrl' => rotating_ads_lang('rotating_ads_editor_image_url', 'Image URL'),
        'destinationUrl' => rotating_ads_lang('rotating_ads_editor_destination_url', 'Destination URL'),
        'altText' => rotating_ads_lang('rotating_ads_editor_alt_text', 'Alt text'),
        'enabled' => rotating_ads_lang('rotating_ads_editor_enabled', 'Enabled'),
        'actions' => rotating_ads_lang('rotating_ads_editor_actions', 'Actions'),
        'addAd' => rotating_ads_lang('rotating_ads_editor_add_ad', 'Add ad'),
        'remove' => rotating_ads_lang('rotating_ads_editor_remove', 'Remove')
    );
}

function rotating_ads_current_user_hidden()
{
    global $mybb;

    $hidden_groups = rotating_ads_parse_id_list(isset($mybb->settings['rotating_ads_hidden_groups'])
        ? $mybb->settings['rotating_ads_hidden_groups']
        : '');

    if (empty($hidden_groups)) {
        return false;
    }

    $user = isset($mybb->user) && is_array($mybb->user) ? $mybb->user : array();
    $user_groups = rotating_ads_parse_id_list(isset($user['additionalgroups']) ? $user['additionalgroups'] : '');

    if (!empty($user['usergroup'])) {
        $user_groups[] = (int)$user['usergroup'];
    }

    return (bool)array_intersect(array_unique($user_groups), $hidden_groups);
}

function rotating_ads_parse_id_list($value)
{
    $ids = array();
    $parts = preg_split('/[,\s]+/', (string)$value);

    foreach ($parts as $part) {
        if (!ctype_digit($part)) {
            continue;
        }

        $id = (int)$part;

        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function rotating_ads_lang($key, $fallback)
{
    global $lang;

    return isset($lang->$key) ? $lang->$key : $fallback;
}

function rotating_ads_rebuild_settings()
{
    if (!function_exists('rebuild_settings')) {
        require_once MYBB_ROOT . 'inc/functions.php';
    }

    rebuild_settings();
}
