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
        'version' => '0.8.1',
        'compatibility' => '18*',
        'license' => 'GPL-3.0-only'
    );
}

function rotating_ads_install()
{
    rotating_ads_ensure_storage();
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

    if ($db->table_exists('rotating_ads')) {
        $db->drop_table('rotating_ads');
    }
}

function rotating_ads_activate()
{
    rotating_ads_ensure_storage();
    rotating_ads_migrate_inventory_settings();
    rotating_ads_ensure_settings();
    rotating_ads_refresh_stylesheets();

    if (function_exists('change_admin_permission')) {
        change_admin_permission('config', 'rotating_ads');
    }

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

function rotating_ads_ensure_storage()
{
    global $db;

    if (!$db->table_exists('rotating_ads')) {
        $collation = $db->build_create_table_collation();

        switch ($db->type) {
            case 'pgsql':
                $db->write_query('CREATE TABLE ' . TABLE_PREFIX . "rotating_ads (
                    aid serial,
                    slot varchar(10) NOT NULL default 'square',
                    image_url varchar(500) NOT NULL default '',
                    destination_url varchar(500) NOT NULL default '',
                    alt_text varchar(255) NOT NULL default '',
                    enabled smallint NOT NULL default '1',
                    display_order integer NOT NULL default '0',
                    weight integer NOT NULL default '1',
                    start_at integer NOT NULL default '0',
                    end_at integer NOT NULL default '0',
                    max_impressions bigint NOT NULL default '0',
                    max_clicks bigint NOT NULL default '0',
                    impressions bigint NOT NULL default '0',
                    clicks bigint NOT NULL default '0',
                    country_mode varchar(10) NOT NULL default 'all',
                    country_codes varchar(1000) NOT NULL default '',
                    PRIMARY KEY (aid)
                )");
                break;
            case 'sqlite':
                $db->write_query('CREATE TABLE ' . TABLE_PREFIX . "rotating_ads (
                    aid INTEGER PRIMARY KEY,
                    slot varchar(10) NOT NULL default 'square',
                    image_url varchar(500) NOT NULL default '',
                    destination_url varchar(500) NOT NULL default '',
                    alt_text varchar(255) NOT NULL default '',
                    enabled tinyint(1) NOT NULL default '1',
                    display_order int NOT NULL default '0',
                    weight int NOT NULL default '1',
                    start_at int NOT NULL default '0',
                    end_at int NOT NULL default '0',
                    max_impressions bigint NOT NULL default '0',
                    max_clicks bigint NOT NULL default '0',
                    impressions bigint NOT NULL default '0',
                    clicks bigint NOT NULL default '0',
                    country_mode varchar(10) NOT NULL default 'all',
                    country_codes varchar(1000) NOT NULL default ''
                )");
                break;
            default:
                $db->write_query('CREATE TABLE ' . TABLE_PREFIX . "rotating_ads (
                    aid int unsigned NOT NULL auto_increment,
                    slot varchar(10) NOT NULL default 'square',
                    image_url varchar(500) NOT NULL default '',
                    destination_url varchar(500) NOT NULL default '',
                    alt_text varchar(255) NOT NULL default '',
                    enabled tinyint(1) NOT NULL default '1',
                    display_order int NOT NULL default '0',
                    weight smallint unsigned NOT NULL default '1',
                    start_at int unsigned NOT NULL default '0',
                    end_at int unsigned NOT NULL default '0',
                    max_impressions bigint unsigned NOT NULL default '0',
                    max_clicks bigint unsigned NOT NULL default '0',
                    impressions bigint unsigned NOT NULL default '0',
                    clicks bigint unsigned NOT NULL default '0',
                    country_mode varchar(10) NOT NULL default 'all',
                    country_codes varchar(1000) NOT NULL default '',
                    PRIMARY KEY (aid),
                    KEY slot_enabled (slot, enabled)
                ) ENGINE=MyISAM{$collation}");
                break;
        }
    }

    $integer = $db->type === 'pgsql' ? 'integer' : 'int';
    $bigint = 'bigint';
    $columns = array(
        'weight' => "{$integer} NOT NULL default '1'",
        'start_at' => "{$integer} NOT NULL default '0'",
        'end_at' => "{$integer} NOT NULL default '0'",
        'max_impressions' => "{$bigint} NOT NULL default '0'",
        'max_clicks' => "{$bigint} NOT NULL default '0'",
        'impressions' => "{$bigint} NOT NULL default '0'",
        'clicks' => "{$bigint} NOT NULL default '0'",
        'country_mode' => "varchar(10) NOT NULL default 'all'",
        'country_codes' => "varchar(1000) NOT NULL default ''"
    );
    foreach ($columns as $name => $definition) {
        if (!$db->field_exists($name, 'rotating_ads')) {
            $db->add_column('rotating_ads', $name, $definition);
        }
    }
}

function rotating_ads_migrate_inventory_settings()
{
    global $db;

    if (!$db->table_exists('rotating_ads')) {
        return;
    }

    $count_query = $db->simple_select('rotating_ads', 'COUNT(aid) AS ads');
    $count = $db->fetch_field($count_query, 'ads');
    if ((int)$count > 0) {
        return;
    }

    $legacy_settings = array(
        'rotating_ads_square_inventory' => 'square',
        'rotating_ads_banner_inventory' => 'banner'
    );

    foreach ($legacy_settings as $setting_name => $slot) {
        $query = $db->simple_select(
            'settings',
            'value',
            "name='" . $db->escape_string($setting_name) . "'",
            array('limit' => 1)
        );
        $setting = $db->fetch_array($query);

        foreach (rotating_ads_parse_inventory_records(isset($setting['value']) ? $setting['value'] : '') as $order => $ad) {
            $ad['slot'] = $slot;
            $ad['display_order'] = $order + 1;
            $db->insert_query('rotating_ads', rotating_ads_prepare_ad_for_database($ad));
        }
    }
}

function rotating_ads_deactivate()
{
    rotating_ads_remove_stylesheets();

    if (function_exists('change_admin_permission')) {
        change_admin_permission('config', 'rotating_ads', -1);
    }

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
            'description' => rotating_ads_lang('rotating_ads_settings_description', 'General display and rotation settings. Manage individual ads from Configuration > Rotating Ads.'),
            'disporder' => 1,
            'isdefault' => 0
        ), "gid='{$gid}'");
    } else {
        $gid = (int)$db->insert_query('settinggroups', array(
            'name' => 'rotating_ads',
            'title' => rotating_ads_lang('rotating_ads_name', 'Rotating Ads'),
            'description' => rotating_ads_lang('rotating_ads_settings_description', 'General display and rotation settings. Manage individual ads from Configuration > Rotating Ads.'),
            'disporder' => 1,
            'isdefault' => 0
        ));
    }

    $settings = array(
        array(
            'name' => 'rotating_ads_template_variables',
            'title' => rotating_ads_lang('rotating_ads_template_variables', 'Template variables'),
            'description' => rotating_ads_lang('rotating_ads_template_variables_description', 'Place either variable in the theme template where that ad slot should appear. The plugin does not choose a location automatically.'),
            'optionscode' => "php\n<code>{&#36;rotating_ads_square}</code><br /><code>{&#36;rotating_ads_banner}</code>",
            'value' => '',
            'disporder' => 1,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_sponsor_label',
            'title' => rotating_ads_lang('rotating_ads_sponsor_label', 'Sponsor label'),
            'description' => rotating_ads_lang('rotating_ads_sponsor_label_description', 'Optional label displayed above each ad. Leave blank to hide the label.'),
            'optionscode' => 'text',
            'value' => rotating_ads_lang('rotating_ads_default_sponsor_label', 'Sponsored'),
            'disporder' => 2,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_hidden_groups',
            'title' => rotating_ads_lang('rotating_ads_hidden_groups', 'Hide ads from usergroups'),
            'description' => rotating_ads_lang('rotating_ads_hidden_groups_description', 'Comma-separated primary or additional usergroup IDs that should not see rotating ads. Leave blank to show ads to all groups.'),
            'optionscode' => 'text',
            'value' => '',
            'disporder' => 3,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_enable_css',
            'title' => rotating_ads_lang('rotating_ads_enable_css', 'Load plugin CSS'),
            'description' => rotating_ads_lang('rotating_ads_enable_css_description', 'Load the small default stylesheet. Disable this if your theme provides its own ad styling.'),
            'optionscode' => 'yesno',
            'value' => '1',
            'disporder' => 4,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_open_new_tab',
            'title' => rotating_ads_lang('rotating_ads_open_new_tab', 'Open ads in a new tab'),
            'description' => rotating_ads_lang('rotating_ads_open_new_tab_description', 'Open advertisement links in a new browser tab.'),
            'optionscode' => 'yesno',
            'value' => '1',
            'disporder' => 5,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_enable_rotation',
            'title' => rotating_ads_lang('rotating_ads_enable_rotation', 'Rotate ads while viewing a page'),
            'description' => rotating_ads_lang('rotating_ads_enable_rotation_description', 'Cycle through enabled ads without requiring a page reload. Requires JavaScript; visitors without JavaScript keep the normal static output. Slots with fewer than two enabled ads also stay static.'),
            'optionscode' => 'yesno',
            'value' => '0',
            'disporder' => 6,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_rotation_min_seconds',
            'title' => rotating_ads_lang('rotating_ads_rotation_min_seconds', 'Minimum rotation seconds'),
            'description' => rotating_ads_lang('rotating_ads_rotation_min_seconds_description', 'Minimum seconds an ad remains visible before the next rotation.'),
            'optionscode' => 'numeric',
            'value' => '15',
            'disporder' => 7,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_rotation_max_seconds',
            'title' => rotating_ads_lang('rotating_ads_rotation_max_seconds', 'Maximum rotation seconds'),
            'description' => rotating_ads_lang('rotating_ads_rotation_max_seconds_description', 'Maximum seconds an ad remains visible before the next rotation. Values below the minimum are treated as the minimum.'),
            'optionscode' => 'numeric',
            'value' => '30',
            'disporder' => 8,
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

    $db->delete_query(
        'settings',
        "name IN ('rotating_ads_square_inventory', 'rotating_ads_banner_inventory')"
    );

    rotating_ads_rebuild_settings();
}

$plugins->add_hook('global_start', 'rotating_ads_build_output');
$plugins->add_hook('misc_start', 'rotating_ads_track_request');

function rotating_ads_track_request()
{
    global $mybb, $db;

    $action = isset($mybb->input['action']) ? $mybb->input['action'] : '';
    if ($action !== 'rotating_ads_click' && $action !== 'rotating_ads_image') {
        return;
    }

    if ($db->table_exists('rotating_ads') && !$db->field_exists('impressions', 'rotating_ads')) {
        rotating_ads_ensure_storage();
    }

    $aid = isset($mybb->input['aid']) ? (int)$mybb->input['aid'] : 0;
    $query = $db->simple_select('rotating_ads', '*', "aid='{$aid}'", array('limit' => 1));
    $ad = $db->fetch_array($query);
    $target_field = $action === 'rotating_ads_click' ? 'destination_url' : 'image_url';

    if (empty($ad['aid']) || !rotating_ads_valid_url($ad[$target_field])) {
        error(rotating_ads_lang('rotating_ads_not_found', 'The selected ad could not be found.'));
    }

    if ($action === 'rotating_ads_click') {
        rotating_ads_increment_metric($aid, 'clicks');
    } else {
        rotating_ads_increment_metric($aid, 'impressions');
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Location: ' . $ad[$target_field], true, 302);
    exit;
}

function rotating_ads_increment_metric($aid, $metric)
{
    global $db;

    $metric = $metric === 'clicks' ? 'clicks' : 'impressions';
    $limit = $metric === 'clicks' ? 'max_clicks' : 'max_impressions';
    $db->write_query(
        'UPDATE ' . TABLE_PREFIX . "rotating_ads SET {$metric}={$metric}+1"
        . " WHERE aid='" . (int)$aid . "' AND ({$limit}=0 OR {$metric}<{$limit})"
    );
}
function rotating_ads_build_output()
{
    global $mybb, $rotating_ads_assets, $rotating_ads_square, $rotating_ads_banner;

    $rotating_ads_assets = '';
    $rotating_ads_square = '';
    $rotating_ads_banner = '';

    $action = isset($mybb->input['action']) ? $mybb->input['action'] : '';
    if ($action === 'rotating_ads_click' || $action === 'rotating_ads_image') {
        return;
    }

    if (rotating_ads_current_user_hidden()) {
        return;
    }

    $square_ads = rotating_ads_get_ads('square');
    $banner_ads = rotating_ads_get_ads('banner');
    $label = isset($mybb->settings['rotating_ads_sponsor_label'])
        ? trim((string)$mybb->settings['rotating_ads_sponsor_label'])
        : rotating_ads_lang('rotating_ads_default_sponsor_label', 'Sponsored');
    $open_new_tab = rotating_ads_setting_enabled('rotating_ads_open_new_tab', true);
    $rotation_options = rotating_ads_rotation_options();

    if ($rotation_options['enabled'] && (count($square_ads) > 1 || count($banner_ads) > 1)) {
        $asset_base = isset($mybb->asset_url) && $mybb->asset_url !== '' ? $mybb->asset_url : $mybb->settings['bburl'];
        $asset_url = rtrim($asset_base, '/');
        $script_url = $asset_url . '/jscripts/rotating-ads.js?ver=080';
        $rotating_ads_assets = '<script type="text/javascript" src="' . htmlspecialchars_uni($script_url) . '" defer="defer"></script>';
    }

    $rotating_ads_square = rotating_ads_render_slot('square', $square_ads, $label, $open_new_tab, $rotation_options);
    $rotating_ads_banner = rotating_ads_render_slot('banner', $banner_ads, $label, $open_new_tab, $rotation_options);
}

function rotating_ads_get_ads($slot)
{
    global $db;

    $ads = array();
    if (!$db->table_exists('rotating_ads')) {
        return $ads;
    }

    $slot = $slot === 'banner' ? 'banner' : 'square';
    $query = $db->simple_select(
        'rotating_ads',
        '*',
        "slot='" . $db->escape_string($slot) . "' AND enabled='1'",
        array('order_by' => 'display_order, aid', 'order_dir' => 'ASC')
    );

    while ($ad = $db->fetch_array($query)) {
        if (!array_key_exists('weight', $ad)) {
            rotating_ads_ensure_storage();
            return rotating_ads_get_ads($slot);
        }

        if (!rotating_ads_valid_url($ad['image_url']) || !rotating_ads_valid_url($ad['destination_url'])) {
            continue;
        }

        if (rotating_ads_ad_is_eligible($ad)) {
            $ads[] = $ad;
        }
    }

    return $ads;
}

function rotating_ads_parse_inventory($value)
{
    $ads = array();

    foreach (rotating_ads_parse_inventory_records($value) as $ad) {
        if (!$ad['enabled']) {
            continue;
        }

        $ads[] = array(
            'image_url' => $ad['image_url'],
            'destination_url' => $ad['destination_url'],
            'alt_text' => $ad['alt_text']
        );
    }

    return $ads;
}

function rotating_ads_parse_inventory_records($value)
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

        if (!rotating_ads_valid_url($image_url) || !rotating_ads_valid_url($destination_url)) {
            continue;
        }

        $ads[] = array(
            'image_url' => $image_url,
            'destination_url' => $destination_url,
            'alt_text' => isset($parts[2]) ? $parts[2] : '',
            'enabled' => !isset($parts[3]) || (int)$parts[3] === 1
        );
    }

    return $ads;
}

function rotating_ads_prepare_ad_for_database($ad)
{
    global $db;

    return array(
        'slot' => $db->escape_string($ad['slot'] === 'banner' ? 'banner' : 'square'),
        'image_url' => $db->escape_string(trim((string)$ad['image_url'])),
        'destination_url' => $db->escape_string(trim((string)$ad['destination_url'])),
        'alt_text' => $db->escape_string(trim((string)$ad['alt_text'])),
        'enabled' => empty($ad['enabled']) ? 0 : 1,
        'display_order' => isset($ad['display_order']) ? max(0, (int)$ad['display_order']) : 0,
        'weight' => isset($ad['weight']) ? min(100, max(1, (int)$ad['weight'])) : 1,
        'start_at' => isset($ad['start_at']) ? max(0, (int)$ad['start_at']) : 0,
        'end_at' => isset($ad['end_at']) ? max(0, (int)$ad['end_at']) : 0,
        'max_impressions' => isset($ad['max_impressions']) ? max(0, (int)$ad['max_impressions']) : 0,
        'max_clicks' => isset($ad['max_clicks']) ? max(0, (int)$ad['max_clicks']) : 0,
        'country_mode' => $db->escape_string(rotating_ads_country_mode(isset($ad['country_mode']) ? $ad['country_mode'] : 'all')),
        'country_codes' => $db->escape_string(rotating_ads_normalize_country_codes(isset($ad['country_codes']) ? $ad['country_codes'] : ''))
    );
}

function rotating_ads_valid_url($url)
{
    $url = trim((string)$url);
    if (preg_match('#^/(?!/)#', $url) && strpos($url, "\\") === false && !preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return true;
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

    return ($scheme === 'http' || $scheme === 'https') && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function rotating_ads_country_mode($mode)
{
    return in_array($mode, array('allow', 'block'), true) ? $mode : 'all';
}

function rotating_ads_normalize_country_codes($value)
{
    $codes = preg_split('/[\s,]+/', strtoupper(trim((string)$value)));
    $normalized = array();
    foreach ($codes as $code) {
        if (preg_match('/^[A-Z]{2}$/', $code) && !in_array($code, $normalized, true)) {
            $normalized[] = $code;
        }
    }

    return implode(',', $normalized);
}

function rotating_ads_country_code($server = null)
{
    $server = is_array($server) ? $server : $_SERVER;
    foreach (array('HTTP_CF_IPCOUNTRY', 'GEOIP_COUNTRY_CODE', 'HTTP_X_APPENGINE_COUNTRY') as $key) {
        $code = isset($server[$key]) ? strtoupper(trim($server[$key])) : '';
        if (preg_match('/^[A-Z]{2}$/', $code)) {
            return $code;
        }
    }

    return '';
}

function rotating_ads_ad_is_eligible($ad, $now = null, $country = null)
{
    $now = $now === null ? (defined('TIME_NOW') ? TIME_NOW : time()) : (int)$now;
    if (empty($ad['enabled'])) {
        return false;
    }
    if (!empty($ad['start_at']) && $now < (int)$ad['start_at']) {
        return false;
    }
    if (!empty($ad['end_at']) && $now > (int)$ad['end_at']) {
        return false;
    }
    if (!empty($ad['max_impressions']) && (int)$ad['impressions'] >= (int)$ad['max_impressions']) {
        return false;
    }
    if (!empty($ad['max_clicks']) && (int)$ad['clicks'] >= (int)$ad['max_clicks']) {
        return false;
    }

    $mode = rotating_ads_country_mode(isset($ad['country_mode']) ? $ad['country_mode'] : 'all');
    if ($mode === 'all') {
        return true;
    }

    $country = $country === null ? rotating_ads_country_code() : strtoupper(trim((string)$country));
    $codes = explode(',', rotating_ads_normalize_country_codes(isset($ad['country_codes']) ? $ad['country_codes'] : ''));
    $matched = $country !== '' && in_array($country, $codes, true);

    return $mode === 'allow' ? $matched : !$matched;
}

function rotating_ads_weighted_index($ads, $exclude = -1)
{
    $total = 0;
    foreach ($ads as $index => $ad) {
        if ((int)$index !== (int)$exclude) {
            $total += max(1, isset($ad['weight']) ? (int)$ad['weight'] : 1);
        }
    }
    if ($total < 1) {
        return 0;
    }

    $pick = mt_rand(1, $total);
    foreach ($ads as $index => $ad) {
        if ((int)$index === (int)$exclude) {
            continue;
        }
        $pick -= max(1, isset($ad['weight']) ? (int)$ad['weight'] : 1);
        if ($pick <= 0) {
            return $index;
        }
    }

    return 0;
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
        $first_index = rotating_ads_weighted_index($ads);
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

    $ad = $ads[rotating_ads_weighted_index($ads)];

    return '<aside class="rotating-ad rotating-ad--' . $format . '">'
        . $title
        . rotating_ads_render_link($ad, $open_new_tab)
        . '</aside>';
}

function rotating_ads_render_link($ad, $open_new_tab = true, $hidden = false)
{
    global $mybb;

    $target = $open_new_tab ? ' target="_blank"' : '';
    $rel = $open_new_tab ? 'sponsored noopener noreferrer' : 'sponsored';
    $hidden_attribute = $hidden ? ' hidden="hidden"' : '';
    $aid = isset($ad['aid']) ? (int)$ad['aid'] : 0;
    $weight = isset($ad['weight']) ? max(1, (int)$ad['weight']) : 1;
    $destination_url = $ad['destination_url'];
    $image_url = $ad['image_url'];
    $image_attribute = ' src="' . htmlspecialchars_uni($image_url) . '"';
    $data = ' data-rotating-ads-weight="' . $weight . '"';

    if ($aid > 0) {
        $base_url = rtrim($mybb->settings['bburl'], '/') . '/misc.php';
        $destination_url = $base_url . '?action=rotating_ads_click&aid=' . $aid;
        $tracked_image_url = $base_url . '?action=rotating_ads_image&aid=' . $aid;
        $image_attribute = $hidden
            ? ' data-src="' . htmlspecialchars_uni($tracked_image_url) . '"'
            : ' src="' . htmlspecialchars_uni($tracked_image_url) . '"';
        $data .= ' data-rotating-ads-aid="' . $aid . '"';
    }

    return '<a class="rotating-ad__link" href="' . htmlspecialchars_uni($destination_url) . '"' . $target . ' rel="' . $rel . '"' . $hidden_attribute . $data . '>'
        . '<img class="rotating-ad__image"' . $image_attribute . ' alt="' . htmlspecialchars_uni($ad['alt_text']) . '" loading="lazy" />'
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
    $plugins->add_hook('admin_config_action_handler', 'rotating_ads_admin_action_handler');
    $plugins->add_hook('admin_config_menu', 'rotating_ads_admin_menu');
    $plugins->add_hook('admin_config_permissions', 'rotating_ads_admin_permissions');
    $plugins->add_hook('admin_load', 'rotating_ads_admin_page');
    $plugins->add_hook('admin_style_themes_add_commit', 'rotating_ads_refresh_stylesheets');
    $plugins->add_hook('admin_style_themes_import_commit', 'rotating_ads_refresh_stylesheets');
    $plugins->add_hook('admin_style_themes_duplicate_commit', 'rotating_ads_refresh_stylesheets');
    $plugins->add_hook('admin_config_settings_change', 'rotating_ads_refresh_stylesheets');
}

function rotating_ads_admin_action_handler(&$actions)
{
    $actions['rotating_ads'] = array(
        'active' => 'rotating_ads',
        'file' => 'settings.php'
    );
}

function rotating_ads_admin_menu(&$sub_menu)
{
    global $lang;

    $lang->load('rotating_ads');
    $sub_menu['185'] = array(
        'id' => 'rotating_ads',
        'title' => rotating_ads_lang('rotating_ads_manage_ads', 'Rotating Ads'),
        'link' => 'index.php?module=config-rotating_ads'
    );
}

function rotating_ads_admin_permissions(&$admin_permissions)
{
    global $lang;

    $lang->load('rotating_ads');
    $admin_permissions['rotating_ads'] = rotating_ads_lang('rotating_ads_can_manage_ads', 'Can manage rotating ads?');
}

function rotating_ads_admin_page()
{
    global $mybb, $page, $db, $lang;

    if (!isset($page) || $page->active_action !== 'rotating_ads') {
        return;
    }

    $had_storage = $db->table_exists('rotating_ads');
    rotating_ads_ensure_storage();
    if (!$had_storage) {
        rotating_ads_migrate_inventory_settings();
        rotating_ads_ensure_settings();
    }

    $lang->load('rotating_ads');

    $action = isset($mybb->input['action']) ? $mybb->input['action'] : '';
    $aid = isset($mybb->input['aid']) ? (int)$mybb->input['aid'] : 0;

    if ($action === 'delete') {
        $query = $db->simple_select('rotating_ads', 'aid, alt_text', "aid='{$aid}'", array('limit' => 1));
        $ad = $db->fetch_array($query);
        if (empty($ad['aid'])) {
            flash_message(rotating_ads_lang('rotating_ads_not_found', 'The selected ad could not be found.'), 'error');
            admin_redirect('index.php?module=config-rotating_ads');
        }

        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));
            $db->delete_query('rotating_ads', "aid='{$aid}'", 1);
            flash_message(rotating_ads_lang('rotating_ads_deleted', 'The ad was deleted.'), 'success');
            admin_redirect('index.php?module=config-rotating_ads');
        }

        $page->output_confirm_action(
            'index.php?module=config-rotating_ads&amp;action=delete&amp;aid=' . $aid,
            rotating_ads_lang('rotating_ads_delete_confirm', 'Delete this ad?'),
            rotating_ads_lang('rotating_ads_delete_ad', 'Delete ad')
        );
        exit;
    }

    if ($action === 'add' || $action === 'edit') {
        rotating_ads_admin_form($action, $aid);
        exit;
    }

    $page->add_breadcrumb_item(rotating_ads_lang('rotating_ads_manage_ads', 'Rotating Ads'));
    $page->output_header(rotating_ads_lang('rotating_ads_manage_ads', 'Rotating Ads'));

    $sub_tabs = rotating_ads_admin_tabs();
    $page->output_nav_tabs($sub_tabs, 'rotating_ads');

    $table = new Table;
    $table->construct_header(rotating_ads_lang('rotating_ads_ad', 'Ad'));
    $table->construct_header(rotating_ads_lang('rotating_ads_slot', 'Slot'), array('width' => '100'));
    $table->construct_header(rotating_ads_lang('rotating_ads_delivery', 'Delivery'), array('width' => '180'));
    $table->construct_header(rotating_ads_lang('rotating_ads_metrics', 'Metrics'), array('width' => '150'));
    $table->construct_header($lang->controls, array('width' => '100', 'class' => 'align_center'));

    $query = $db->simple_select('rotating_ads', '*', '', array('order_by' => 'slot, aid', 'order_dir' => 'ASC'));
    $has_ads = false;
    while ($ad = $db->fetch_array($query)) {
        $has_ads = true;
        $aid = (int)$ad['aid'];
        $name = trim($ad['alt_text']) !== '' ? $ad['alt_text'] : $ad['image_url'];
        $edit_url = 'index.php?module=config-rotating_ads&amp;action=edit&amp;aid=' . $aid;

        $table->construct_cell('<strong><a href="' . $edit_url . '">' . htmlspecialchars_uni($name) . '</a></strong><br /><small>' . htmlspecialchars_uni($ad['destination_url']) . '</small>');
        $table->construct_cell(ucfirst(htmlspecialchars_uni($ad['slot'])));
        $table->construct_cell(rotating_ads_admin_delivery_summary($ad));
        $table->construct_cell(rotating_ads_admin_metrics_summary($ad));

        $popup = new PopupMenu('rotating_ad_' . $aid, $lang->options);
        $popup->add_item($lang->edit, $edit_url);
        $popup->add_item(
            $lang->delete,
            'index.php?module=config-rotating_ads&amp;action=delete&amp;aid=' . $aid
        );
        $table->construct_cell($popup->fetch(), array('class' => 'align_center'));
        $table->construct_row();
    }

    if (!$has_ads) {
        $table->construct_cell(rotating_ads_lang('rotating_ads_no_ads', 'No ads have been added yet.'), array('colspan' => 5));
        $table->construct_row();
    }

    $table->output(rotating_ads_lang('rotating_ads_manage_ads', 'Rotating Ads'));
    $page->output_footer();
    exit;
}

function rotating_ads_admin_tabs()
{
    return array(
        'rotating_ads' => array(
            'title' => rotating_ads_lang('rotating_ads_manage_ads', 'Manage ads'),
            'link' => 'index.php?module=config-rotating_ads',
            'description' => rotating_ads_lang('rotating_ads_manage_ads_description', 'Manage square and banner ads.')
        ),
        'rotating_ads_add' => array(
            'title' => rotating_ads_lang('rotating_ads_add_ad', 'Add ad'),
            'link' => 'index.php?module=config-rotating_ads&amp;action=add',
            'description' => rotating_ads_lang('rotating_ads_add_ad_description', 'Add a square or banner ad.')
        )
    );
}

function rotating_ads_admin_form($action, $aid = 0)
{
    global $mybb, $page, $db, $lang;

    $editing = $action === 'edit';
    $ad = array(
        'slot' => 'square',
        'image_url' => '',
        'destination_url' => '',
        'alt_text' => '',
        'enabled' => 1,
        'display_order' => 0,
        'weight' => 1,
        'start_at' => 0,
        'end_at' => 0,
        'max_impressions' => 0,
        'max_clicks' => 0,
        'impressions' => 0,
        'clicks' => 0,
        'country_mode' => 'all',
        'country_codes' => ''
    );

    if ($editing) {
        $query = $db->simple_select('rotating_ads', '*', "aid='" . (int)$aid . "'", array('limit' => 1));
        $ad = $db->fetch_array($query);
        if (empty($ad['aid'])) {
            flash_message(rotating_ads_lang('rotating_ads_not_found', 'The selected ad could not be found.'), 'error');
            admin_redirect('index.php?module=config-rotating_ads');
        }
    }

    $errors = array();
    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));
        $current_impressions = isset($ad['impressions']) ? (int)$ad['impressions'] : 0;
        $current_clicks = isset($ad['clicks']) ? (int)$ad['clicks'] : 0;
        $ad = array(
            'slot' => $mybb->get_input('slot') === 'banner' ? 'banner' : 'square',
            'image_url' => trim($mybb->get_input('image_url')),
            'destination_url' => trim($mybb->get_input('destination_url')),
            'alt_text' => trim($mybb->get_input('alt_text')),
            'enabled' => $mybb->get_input('enabled') ? 1 : 0,
            'display_order' => 0,
            'weight' => min(100, max(1, (int)$mybb->get_input('weight'))),
            'start_at' => rotating_ads_parse_admin_date($mybb->get_input('start_date'), false),
            'end_at' => rotating_ads_parse_admin_date($mybb->get_input('end_date'), true),
            'max_impressions' => max(0, (int)$mybb->get_input('max_impressions')),
            'max_clicks' => max(0, (int)$mybb->get_input('max_clicks')),
            'country_mode' => rotating_ads_country_mode($mybb->get_input('country_mode')),
            'country_codes' => rotating_ads_normalize_country_codes($mybb->get_input('country_codes')),
            'reset_metrics' => $mybb->get_input('reset_metrics') ? 1 : 0,
            'impressions' => $current_impressions,
            'clicks' => $current_clicks
        );

        if (!rotating_ads_valid_url($ad['image_url'])) {
            $errors[] = rotating_ads_lang('rotating_ads_invalid_image_url', 'Enter an HTTP(S) image URL or a site-relative path beginning with /.');
        }
        if (!rotating_ads_valid_url($ad['destination_url'])) {
            $errors[] = rotating_ads_lang('rotating_ads_invalid_destination_url', 'Enter an HTTP(S) destination URL or a site-relative path beginning with /.');
        }
        if (trim($mybb->get_input('start_date')) !== '' && !$ad['start_at']) {
            $errors[] = rotating_ads_lang('rotating_ads_invalid_start_date', 'Enter the start date as YYYY-MM-DD.');
        }
        if (trim($mybb->get_input('end_date')) !== '' && !$ad['end_at']) {
            $errors[] = rotating_ads_lang('rotating_ads_invalid_end_date', 'Enter the end date as YYYY-MM-DD.');
        }
        if ($ad['start_at'] && $ad['end_at'] && $ad['end_at'] < $ad['start_at']) {
            $errors[] = rotating_ads_lang('rotating_ads_invalid_date_range', 'The end date must be on or after the start date.');
        }
        if ($ad['country_mode'] !== 'all' && $ad['country_codes'] === '') {
            $errors[] = rotating_ads_lang('rotating_ads_country_codes_required', 'Enter at least one two-letter country code for country targeting.');
        }

        if (empty($errors)) {
            $data = rotating_ads_prepare_ad_for_database($ad);
            if ($editing) {
                $db->update_query('rotating_ads', $data, "aid='" . (int)$aid . "'", 1);
                if (!empty($ad['reset_metrics'])) {
                    $db->update_query('rotating_ads', array('impressions' => 0, 'clicks' => 0), "aid='" . (int)$aid . "'", 1);
                }
                $message = rotating_ads_lang('rotating_ads_updated', 'The ad was updated.');
            } else {
                $aid = (int)$db->insert_query('rotating_ads', $data);
                $message = rotating_ads_lang('rotating_ads_added', 'The ad was added.');
            }

            flash_message($message, 'success');
            admin_redirect('index.php?module=config-rotating_ads&amp;highlight=' . $aid);
        }
    }

    $title = $editing
        ? rotating_ads_lang('rotating_ads_edit_ad', 'Edit ad')
        : rotating_ads_lang('rotating_ads_add_ad', 'Add ad');
    $page->add_breadcrumb_item(rotating_ads_lang('rotating_ads_manage_ads', 'Rotating Ads'), 'index.php?module=config-rotating_ads');
    $page->add_breadcrumb_item($title);
    $page->output_header($title);
    $page->output_nav_tabs(rotating_ads_admin_tabs(), $editing ? 'rotating_ads' : 'rotating_ads_add');

    if (!empty($errors)) {
        $page->output_inline_error($errors);
    }

    $form = new Form(
        'index.php?module=config-rotating_ads&amp;action=' . $action . ($editing ? '&amp;aid=' . (int)$aid : ''),
        'post',
        'rotating_ads'
    );
    $container = new FormContainer($title);
    $container->output_row(
        rotating_ads_lang('rotating_ads_slot', 'Slot'),
        rotating_ads_lang('rotating_ads_slot_description', 'Choose where this ad can appear.'),
        $form->generate_select_box('slot', array('square' => 'Square', 'banner' => 'Banner'), $ad['slot'])
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_image_url', 'Image URL'),
        '',
        $form->generate_text_box('image_url', $ad['image_url'], array('id' => 'image_url')),
        'image_url'
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_destination_url', 'Destination URL'),
        '',
        $form->generate_text_box('destination_url', $ad['destination_url'], array('id' => 'destination_url')),
        'destination_url'
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_alt_text', 'Alt text'),
        rotating_ads_lang('rotating_ads_alt_text_description', 'Describe the advertisement image for accessibility.'),
        $form->generate_text_box('alt_text', $ad['alt_text'], array('id' => 'alt_text')),
        'alt_text'
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_weight', 'Delivery weight'),
        rotating_ads_lang('rotating_ads_weight_description', 'Higher values make this ad more likely to be selected. Use 1 for equal delivery.'),
        $form->generate_numeric_field('weight', (int)$ad['weight'], array('min' => 1, 'max' => 100))
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_start_date', 'Start date'),
        rotating_ads_lang('rotating_ads_start_date_description', 'Optional UTC date in YYYY-MM-DD format.'),
        $form->generate_text_box('start_date', rotating_ads_format_admin_date($ad['start_at']), array('id' => 'start_date')),
        'start_date'
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_end_date', 'End date'),
        rotating_ads_lang('rotating_ads_end_date_description', 'Optional UTC date in YYYY-MM-DD format. The ad runs through the end of that day.'),
        $form->generate_text_box('end_date', rotating_ads_format_admin_date($ad['end_at']), array('id' => 'end_date')),
        'end_date'
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_max_impressions', 'Impression limit'),
        rotating_ads_lang('rotating_ads_max_impressions_description', 'Stop delivering after this many image views. Use 0 for unlimited.'),
        $form->generate_numeric_field('max_impressions', (int)$ad['max_impressions'], array('min' => 0))
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_max_clicks', 'Click limit'),
        rotating_ads_lang('rotating_ads_max_clicks_description', 'Stop delivering after this many tracked clicks. Use 0 for unlimited.'),
        $form->generate_numeric_field('max_clicks', (int)$ad['max_clicks'], array('min' => 0))
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_country_mode', 'Country targeting'),
        rotating_ads_lang('rotating_ads_country_mode_description', 'Allow or block countries when your server or proxy provides a visitor country code.'),
        $form->generate_select_box('country_mode', array(
            'all' => rotating_ads_lang('rotating_ads_country_all', 'All countries'),
            'allow' => rotating_ads_lang('rotating_ads_country_allow', 'Only listed countries'),
            'block' => rotating_ads_lang('rotating_ads_country_block', 'All except listed countries')
        ), $ad['country_mode'])
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_country_codes', 'Country codes'),
        rotating_ads_lang('rotating_ads_country_codes_description', 'Comma-separated ISO two-letter codes, such as US, CA, GB.'),
        $form->generate_text_box('country_codes', $ad['country_codes'], array('id' => 'country_codes')),
        'country_codes'
    );
    $container->output_row(
        rotating_ads_lang('rotating_ads_status', 'Status'),
        '',
        $form->generate_check_box('enabled', 1, rotating_ads_lang('rotating_ads_enabled', 'Enabled'), array('checked' => !empty($ad['enabled'])))
    );
    if ($editing) {
        $container->output_row(
            rotating_ads_lang('rotating_ads_metrics', 'Metrics'),
            rotating_ads_admin_metrics_summary($ad),
            $form->generate_check_box('reset_metrics', 1, rotating_ads_lang('rotating_ads_reset_metrics', 'Reset impressions and clicks when saving'))
        );
    }
    $container->end();
    $submit_label = $editing
        ? rotating_ads_lang('rotating_ads_save_changes', 'Save changes')
        : rotating_ads_lang('rotating_ads_add_ad', 'Add ad');
    $buttons = array($form->generate_submit_button($submit_label));
    $form->output_submit_wrapper($buttons);
    $form->end();
    $page->output_footer();
}

function rotating_ads_parse_admin_date($value, $end_of_day = false)
{
    $value = trim((string)$value);
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return 0;
    }

    $parts = array_map('intval', explode('-', $value));
    if (!checkdate($parts[1], $parts[2], $parts[0])) {
        return 0;
    }

    return gmmktime($end_of_day ? 23 : 0, $end_of_day ? 59 : 0, $end_of_day ? 59 : 0, $parts[1], $parts[2], $parts[0]);
}

function rotating_ads_format_admin_date($timestamp)
{
    return !empty($timestamp) ? gmdate('Y-m-d', (int)$timestamp) : '';
}

function rotating_ads_admin_delivery_status($ad, $now = null)
{
    $now = $now === null ? (defined('TIME_NOW') ? TIME_NOW : time()) : (int)$now;
    if (empty($ad['enabled'])) {
        return rotating_ads_lang('rotating_ads_status_disabled', 'Disabled');
    }
    if (!empty($ad['start_at']) && $now < (int)$ad['start_at']) {
        return rotating_ads_lang('rotating_ads_status_scheduled', 'Scheduled');
    }
    if (!empty($ad['end_at']) && $now > (int)$ad['end_at']) {
        return rotating_ads_lang('rotating_ads_status_ended', 'Ended');
    }
    if (!empty($ad['max_impressions']) && (int)$ad['impressions'] >= (int)$ad['max_impressions']) {
        return rotating_ads_lang('rotating_ads_status_impression_limit', 'Impression limit reached');
    }
    if (!empty($ad['max_clicks']) && (int)$ad['clicks'] >= (int)$ad['max_clicks']) {
        return rotating_ads_lang('rotating_ads_status_click_limit', 'Click limit reached');
    }

    return rotating_ads_lang('rotating_ads_status_active', 'Active');
}

function rotating_ads_admin_delivery_summary($ad)
{
    $summary = '<strong>' . htmlspecialchars_uni(rotating_ads_admin_delivery_status($ad)) . '</strong>';
    $details = array(rotating_ads_lang('rotating_ads_weight', 'Delivery weight') . ': ' . max(1, (int)$ad['weight']));
    if (!empty($ad['start_at']) || !empty($ad['end_at'])) {
        $details[] = htmlspecialchars_uni(rotating_ads_format_admin_date($ad['start_at']) ?: '...')
            . ' - ' . htmlspecialchars_uni(rotating_ads_format_admin_date($ad['end_at']) ?: '...');
    }
    $country_mode = rotating_ads_country_mode(isset($ad['country_mode']) ? $ad['country_mode'] : 'all');
    if ($country_mode !== 'all') {
        $country_label = $country_mode === 'allow'
            ? rotating_ads_lang('rotating_ads_country_allow_short', 'Only')
            : rotating_ads_lang('rotating_ads_country_block_short', 'Except');
        $details[] = htmlspecialchars_uni($country_label . ': ' . $ad['country_codes']);
    }

    return $summary . '<br /><small>' . implode('<br />', $details) . '</small>';
}

function rotating_ads_admin_metrics_summary($ad)
{
    $impressions = isset($ad['impressions']) ? (int)$ad['impressions'] : 0;
    $clicks = isset($ad['clicks']) ? (int)$ad['clicks'] : 0;
    $max_impressions = isset($ad['max_impressions']) ? (int)$ad['max_impressions'] : 0;
    $max_clicks = isset($ad['max_clicks']) ? (int)$ad['max_clicks'] : 0;
    $ctr = $impressions > 0 ? number_format(($clicks / $impressions) * 100, 2) : '0.00';
    $impression_total = number_format($impressions) . ($max_impressions ? ' / ' . number_format($max_impressions) : '');
    $click_total = number_format($clicks) . ($max_clicks ? ' / ' . number_format($max_clicks) : '');

    return $impression_total . ' ' . rotating_ads_lang('rotating_ads_impressions', 'impressions')
        . '<br />' . $click_total . ' ' . rotating_ads_lang('rotating_ads_clicks', 'clicks')
        . '<br />' . $ctr . '% CTR';
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
