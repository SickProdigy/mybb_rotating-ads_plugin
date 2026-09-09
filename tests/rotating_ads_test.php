<?php

define('IN_MYBB', 1);
define('MYBB_ROOT', __DIR__ . '/../');
define('TIME_NOW', 1788912000);

function rotating_ads_test_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

class RotatingAdsTestPlugins
{
    public $hooks = array();

    public function add_hook($hook, $callback)
    {
        $this->hooks[$hook] = $callback;
    }
}

class RotatingAdsTestQuery
{
    public $rows;
    private $position = 0;

    public function __construct($rows)
    {
        $this->rows = isset($rows[0]) ? array_values($rows) : array($rows);
    }

    public function next()
    {
        if (!isset($this->rows[$this->position])) {
            return array();
        }

        return $this->rows[$this->position++];
    }
}

class RotatingAdsTestDatabase
{
    public $group = array();
    public $settings = array();
    public $themes = array(
        array('tid' => 2),
        array('tid' => 3),
    );
    public $stylesheets = array();
    private $next_setting_id = 1;
    private $next_stylesheet_id = 1;

    public function simple_select($table, $fields, $where, $options = array())
    {
        if ($table === 'settinggroups') {
            return new RotatingAdsTestQuery($this->group);
        }

        if ($table === 'settings') {
            preg_match("/name='([^']+)'/", $where, $matches);
            $name = isset($matches[1]) ? stripslashes($matches[1]) : '';

            return new RotatingAdsTestQuery(isset($this->settings[$name]) ? $this->settings[$name] : array());
        }

        if ($table === 'themes') {
            return new RotatingAdsTestQuery($this->themes);
        }

        if ($table === 'themestylesheets') {
            preg_match("/name='([^']+)'/", $where, $name_matches);
            preg_match("/tid='([0-9]+)'/", $where, $tid_matches);
            $name = isset($name_matches[1]) ? stripslashes($name_matches[1]) : '';
            $tid = isset($tid_matches[1]) ? (int)$tid_matches[1] : 0;
            $rows = array();

            foreach ($this->stylesheets as $stylesheet) {
                if ($stylesheet['name'] === $name && (!$tid || (int)$stylesheet['tid'] === $tid)) {
                    $rows[] = $stylesheet;
                }
            }

            return new RotatingAdsTestQuery($rows);
        }

        return new RotatingAdsTestQuery(array());
    }

    public function fetch_array($query)
    {
        return $query->next();
    }

    public function insert_query($table, $values)
    {
        if ($table === 'settinggroups') {
            $values['gid'] = 12;
            $this->group = $values;
            return 12;
        }

        if ($table === 'themestylesheets') {
            $values['sid'] = $this->next_stylesheet_id++;
            $this->stylesheets[$values['sid']] = $values;

            return $values['sid'];
        }

        $values['sid'] = $this->next_setting_id++;
        $this->settings[$values['name']] = $values;

        return $values['sid'];
    }

    public function update_query($table, $values, $where, $limit = 0)
    {
        if ($table === 'settinggroups') {
            $this->group = array_merge($this->group, $values);
            return;
        }

        if ($table === 'themestylesheets') {
            preg_match("/sid='([0-9]+)'/", $where, $matches);
            $sid = isset($matches[1]) ? (int)$matches[1] : 0;

            if (isset($this->stylesheets[$sid])) {
                $this->stylesheets[$sid] = array_merge($this->stylesheets[$sid], $values);
            }

            return;
        }

        preg_match("/sid='([0-9]+)'/", $where, $matches);
        $sid = isset($matches[1]) ? (int)$matches[1] : 0;

        foreach ($this->settings as $name => $setting) {
            if ((int)$setting['sid'] === $sid) {
                $this->settings[$name] = array_merge($setting, $values);
                return;
            }
        }
    }

    public function delete_query($table, $where)
    {
        if ($table === 'settinggroups') {
            $this->group = array();
        }

        if ($table === 'settings') {
            $this->settings = array();
        }

        if ($table === 'themestylesheets') {
            preg_match("/sid='([0-9]+)'/", $where, $matches);
            $sid = isset($matches[1]) ? (int)$matches[1] : 0;
            unset($this->stylesheets[$sid]);
        }
    }

    public function escape_string($value)
    {
        return addslashes($value);
    }
}

class RotatingAdsTestLang
{
    public $rotating_ads_name = 'Rotating Ads';
    public $rotating_ads_description = 'Description';
    public $rotating_ads_settings_description = 'Settings description';
    public $rotating_ads_square_inventory = 'Square Ads';
    public $rotating_ads_banner_inventory = 'Banner Ads';
    public $rotating_ads_inventory_description = 'Inventory description';
    public $rotating_ads_sponsor_label = 'Sponsor label';
    public $rotating_ads_sponsor_label_description = 'Sponsor label description';
    public $rotating_ads_default_sponsor_label = 'Sponsored';
    public $rotating_ads_hidden_groups = 'Hide ads from usergroups';
    public $rotating_ads_hidden_groups_description = 'Hidden groups description';
    public $rotating_ads_enable_css = 'Load plugin CSS';
    public $rotating_ads_enable_css_description = 'CSS description';
    public $rotating_ads_open_new_tab = 'Open ads in a new tab';
    public $rotating_ads_open_new_tab_description = 'New tab description';
    public $rotating_ads_enable_rotation = 'Rotate ads while viewing a page';
    public $rotating_ads_enable_rotation_description = 'Rotation description';
    public $rotating_ads_rotation_min_seconds = 'Minimum rotation seconds';
    public $rotating_ads_rotation_min_seconds_description = 'Minimum rotation description';
    public $rotating_ads_rotation_max_seconds = 'Maximum rotation seconds';
    public $rotating_ads_rotation_max_seconds_description = 'Maximum rotation description';

    public function load($name)
    {
    }
}

function htmlspecialchars_uni($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function rebuild_settings()
{
}

function cache_stylesheet($tid, $name, $stylesheet)
{
    return true;
}

function update_theme_stylesheet_list($tid)
{
}

$plugins = new RotatingAdsTestPlugins();
$db = new RotatingAdsTestDatabase();
$lang = new RotatingAdsTestLang();
$mybb = (object)array(
    'asset_url' => 'https://static.example.com',
    'settings' => array(
        'bburl' => 'https://example.com/forum',
        'rotating_ads_square_inventory' => '',
        'rotating_ads_banner_inventory' => '',
        'rotating_ads_sponsor_label' => 'Sponsored',
        'rotating_ads_hidden_groups' => '',
        'rotating_ads_enable_css' => '1',
        'rotating_ads_open_new_tab' => '1',
        'rotating_ads_enable_rotation' => '0',
        'rotating_ads_rotation_min_seconds' => '15',
        'rotating_ads_rotation_max_seconds' => '30',
    ),
    'user' => array(
        'usergroup' => 2,
        'additionalgroups' => '',
    ),
);

require dirname(__DIR__) . '/Upload/inc/plugins/rotating_ads.php';

rotating_ads_test_assert(
    isset($plugins->hooks['global_start']) && $plugins->hooks['global_start'] === 'rotating_ads_build_output',
    'plugin should register the global_start hook'
);

$info = rotating_ads_info();
rotating_ads_test_assert(
    $info['name'] === 'Rotating Ads' && $info['version'] === '0.6.2',
    'plugin info should expose localized metadata and version'
);

$inventory = "https://example.com/ad.jpg|https://example.com/|Example <Ad>|1\n"
    . "https://example.com/disabled.jpg|https://example.com/|Disabled|0\n"
    . "ftp://example.com/bad.jpg|https://example.com/|Bad|1\n"
    . "# ignored comment\n";
$ads = rotating_ads_parse_inventory($inventory);
rotating_ads_test_assert(
    count($ads) === 1 && $ads[0]['alt_text'] === 'Example <Ad>',
    'inventory parser should keep only enabled HTTP(S) ads'
);

$rendered = rotating_ads_render_slot('square', $ads, 'Ad & Sponsor', true);
rotating_ads_test_assert(
    strpos($rendered, 'rotating-ad--square') !== false
    && strpos($rendered, 'Ad &amp; Sponsor') !== false
    && strpos($rendered, 'Example &lt;Ad&gt;') !== false
    && strpos($rendered, 'target="_blank"') !== false
    && strpos($rendered, 'rel="sponsored noopener noreferrer"') !== false,
    'rendered ad should escape text and include new-tab attributes'
);

$same_tab = rotating_ads_render_slot('banner', $ads, '', false);
rotating_ads_test_assert(
    strpos($same_tab, 'rotating-ad__title') === false
    && strpos($same_tab, 'target="_blank"') === false
    && strpos($same_tab, 'rel="sponsored"') !== false,
    'rendered ad should support hidden labels and same-tab links'
);

$rotating_ads_multi_inventory = "https://example.com/ad-1.jpg|https://example.com/1|One|1\n"
    . "https://example.com/ad-2.jpg|https://example.com/2|Two|1\n";
$rotating_ads_multi_ads = rotating_ads_parse_inventory($rotating_ads_multi_inventory);
$rotating_ads_rotating_slot = rotating_ads_render_slot(
    'square',
    $rotating_ads_multi_ads,
    'Sponsored',
    true,
    array('enabled' => true, 'min_seconds' => 5, 'max_seconds' => 9)
);
rotating_ads_test_assert(
    strpos($rotating_ads_rotating_slot, 'data-rotating-ads="1"') !== false
    && strpos($rotating_ads_rotating_slot, 'data-rotating-ads-min="5"') !== false
    && strpos($rotating_ads_rotating_slot, 'data-rotating-ads-max="9"') !== false
    && substr_count($rotating_ads_rotating_slot, 'class="rotating-ad__link"') === 2
    && substr_count($rotating_ads_rotating_slot, 'hidden="hidden"') === 1,
    'rotating slots should render all enabled ads with timing data and one visible fallback'
);

rotating_ads_test_assert(
    rotating_ads_parse_id_list('4, 8 8 bad 0') === array(4, 8),
    'ID list parser should keep unique positive numeric IDs'
);

$mybb->settings['rotating_ads_enable_rotation'] = '1';
$mybb->settings['rotating_ads_rotation_min_seconds'] = '12';
$mybb->settings['rotating_ads_rotation_max_seconds'] = '5';
$rotation_options = rotating_ads_rotation_options();
rotating_ads_test_assert(
    $rotation_options['enabled'] === true
    && $rotation_options['min_seconds'] === 12
    && $rotation_options['max_seconds'] === 12,
    'rotation options should clamp maximum seconds to the configured minimum'
);
$mybb->settings['rotating_ads_enable_rotation'] = '0';
$mybb->settings['rotating_ads_rotation_min_seconds'] = '15';
$mybb->settings['rotating_ads_rotation_max_seconds'] = '30';

rotating_ads_ensure_settings();
rotating_ads_test_assert(
    isset($db->settings['rotating_ads_sponsor_label'])
    && isset($db->settings['rotating_ads_hidden_groups'])
    && isset($db->settings['rotating_ads_enable_css'])
    && isset($db->settings['rotating_ads_open_new_tab'])
    && isset($db->settings['rotating_ads_enable_rotation'])
    && isset($db->settings['rotating_ads_rotation_min_seconds'])
    && isset($db->settings['rotating_ads_rotation_max_seconds']),
    'setting synchronization should create polish settings'
);
rotating_ads_test_assert(
    $db->settings['rotating_ads_square_inventory']['value'] === '',
    'fresh installs should not ship a site-specific default ad'
);

$db->settings['rotating_ads_square_inventory']['value'] = 'https://custom.example/ad.png|https://custom.example/|Custom|1';
rotating_ads_ensure_settings();
rotating_ads_test_assert(
    $db->settings['rotating_ads_square_inventory']['value'] === 'https://custom.example/ad.png|https://custom.example/|Custom|1',
    'setting synchronization should preserve existing inventory values'
);

$mybb->settings['rotating_ads_square_inventory'] = 'https://example.com/ad.jpg|https://example.com/|Example|1';
rotating_ads_build_output();
rotating_ads_test_assert(
    $rotating_ads_assets === ''
    && strpos($rotating_ads_square, 'rotating-ad--square') !== false
    && $rotating_ads_banner === '',
    'global output should build populated slots without legacy link assets'
);

rotating_ads_refresh_stylesheets();
rotating_ads_test_assert(
    count($db->stylesheets) === 2
    && isset($db->stylesheets[1]['stylesheet'])
    && strpos($db->stylesheets[1]['stylesheet'], '.rotating-ad') !== false,
    'CSS loading should sync maintained stylesheets into MyBB themes'
);

$mybb->settings['rotating_ads_enable_css'] = '0';
rotating_ads_refresh_stylesheets();
rotating_ads_test_assert(
    count($db->stylesheets) === 0,
    'CSS loading should be removable when disabled'
);

$mybb->settings['rotating_ads_enable_css'] = '1';
$mybb->settings['rotating_ads_enable_rotation'] = '1';
$mybb->settings['rotating_ads_rotation_min_seconds'] = '4';
$mybb->settings['rotating_ads_rotation_max_seconds'] = '8';
$mybb->settings['rotating_ads_square_inventory'] = $rotating_ads_multi_inventory;
rotating_ads_build_output();
rotating_ads_test_assert(
    strpos($rotating_ads_assets, '/jscripts/rotating-ads.js?ver=060') !== false
    && strpos($rotating_ads_square, 'data-rotating-ads="1"') !== false
    && strpos($rotating_ads_square, 'data-rotating-ads-min="4"') !== false
    && strpos($rotating_ads_square, 'data-rotating-ads-max="8"') !== false,
    'global output should add the rotation script and timing data when in-page rotation is enabled'
);

$mybb->settings['rotating_ads_enable_rotation'] = '0';
$mybb->settings['rotating_ads_enable_css'] = '1';
$mybb->settings['rotating_ads_hidden_groups'] = '4, 8';
$mybb->user['usergroup'] = 2;
$mybb->user['additionalgroups'] = '8';
rotating_ads_build_output();
rotating_ads_test_assert(
    $rotating_ads_assets === ''
    && $rotating_ads_square === ''
    && $rotating_ads_banner === '',
    'hidden primary or additional usergroups should not receive assets or ads'
);

$mybb->settings['rotating_ads_hidden_groups'] = '4, 8';
$mybb->user['usergroup'] = 4;
$mybb->user['additionalgroups'] = '';
rotating_ads_build_output();
rotating_ads_test_assert(
    $rotating_ads_assets === ''
    && $rotating_ads_square === ''
    && $rotating_ads_banner === '',
    'hidden primary usergroups should not receive assets or ads'
);

$mybb->user['usergroup'] = 2;
$mybb->user['additionalgroups'] = '';
rotating_ads_build_output();
rotating_ads_test_assert(
    $rotating_ads_assets === ''
    && strpos($rotating_ads_square, 'rotating-ad--square') !== false,
    'users outside hidden groups should still receive ads without legacy link assets'
);

echo "Rotating Ads tests passed.\n";
