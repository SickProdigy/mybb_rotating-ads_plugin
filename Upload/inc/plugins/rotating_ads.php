<?php
/**
 * Rotating Ads
 *
 * Configurable square and banner advertisement slots for MyBB.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

function rotating_ads_info()
{
    return array(
        'name' => 'Rotating Ads',
        'description' => 'Provides independently configurable square and banner advertisement slots.',
        'website' => 'https://www.sickgaming.net',
        'author' => 'Sick Gaming',
        'authorsite' => 'https://www.sickgaming.net',
        'version' => '1.0.0',
        'compatibility' => '18*'
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
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets(
        'headerinclude',
        '#' . preg_quote('{$rotating_ads_assets}') . '#i',
        ''
    );
}

function rotating_ads_ensure_settings()
{
    global $db;

    $query = $db->simple_select('settinggroups', 'gid', "name='rotating_ads'");
    $group = $db->fetch_array($query);

    if (!empty($group['gid'])) {
        $gid = (int)$group['gid'];
    } else {
        $gid = (int)$db->insert_query('settinggroups', array(
            'name' => 'rotating_ads',
            'title' => 'Rotating Ads',
            'description' => 'Settings for square and banner advertisement inventories.',
            'disporder' => 1,
            'isdefault' => 0
        ));
    }

    $settings = array(
        array(
            'name' => 'rotating_ads_square_inventory',
            'title' => 'Square Ads',
            'description' => 'One ad per line: image URL|destination URL|alt text|enabled.',
            'optionscode' => 'textarea',
            'value' => 'https://hostpro.top/images/ad-hostpro.jpg|https://hostpro.top/|HostPro.Top|1',
            'disporder' => 1,
            'gid' => $gid
        ),
        array(
            'name' => 'rotating_ads_banner_inventory',
            'title' => 'Banner Ads',
            'description' => 'One ad per line: image URL|destination URL|alt text|enabled.',
            'optionscode' => 'textarea',
            'value' => '',
            'disporder' => 2,
            'gid' => $gid
        )
    );

    foreach ($settings as $setting) {
        $query = $db->simple_select('settings', 'sid', "name='" . $db->escape_string($setting['name']) . "'");
        $existing = $db->fetch_array($query);

        if (empty($existing['sid'])) {
            $db->insert_query('settings', $setting);
        }
    }

    rotating_ads_rebuild_settings();
}

$plugins->add_hook('global_start', 'rotating_ads_build_output');
function rotating_ads_build_output()
{
    global $mybb, $rotating_ads_assets, $rotating_ads_square, $rotating_ads_banner;

    $asset_url = rtrim($mybb->asset_url, '/');
    $style_url = $asset_url . '/css/rotating-ads.css?ver=100';
    $rotating_ads_assets = '<link rel="stylesheet" href="' . htmlspecialchars_uni($style_url) . '" />';

    $square_ads = rotating_ads_parse_inventory(isset($mybb->settings['rotating_ads_square_inventory'])
        ? $mybb->settings['rotating_ads_square_inventory']
        : '');
    $banner_ads = rotating_ads_parse_inventory(isset($mybb->settings['rotating_ads_banner_inventory'])
        ? $mybb->settings['rotating_ads_banner_inventory']
        : '');

    $rotating_ads_square = rotating_ads_render_slot('square', $square_ads);
    $rotating_ads_banner = rotating_ads_render_slot('banner', $banner_ads);
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

function rotating_ads_render_slot($format, $ads)
{
    if (empty($ads)) {
        return '';
    }

    $ad = $ads[array_rand($ads)];
    $format = $format === 'banner' ? 'banner' : 'square';

    return '<aside class="rotating-ad rotating-ad--' . $format . '">'
        . '<div class="rotating-ad__title">Sponsored</div>'
        . '<a class="rotating-ad__link" href="' . htmlspecialchars_uni($ad['destination_url']) . '" target="_blank" rel="sponsored noopener">'
        . '<img class="rotating-ad__image" src="' . htmlspecialchars_uni($ad['image_url']) . '" alt="' . htmlspecialchars_uni($ad['alt_text']) . '" loading="lazy" />'
        . '</a></aside>';
}

function rotating_ads_rebuild_settings()
{
    if (!function_exists('rebuild_settings')) {
        require_once MYBB_ROOT . 'inc/functions.php';
    }

    rebuild_settings();
}
