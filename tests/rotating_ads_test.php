<?php

define('IN_MYBB', 1);
define('MYBB_ROOT', __DIR__ . '/../');

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
    public $row;

    public function __construct($row)
    {
        $this->row = $row;
    }
}

class RotatingAdsTestDatabase
{
    public $group = array();
    public $settings = array();
    private $next_setting_id = 1;

    public function simple_select($table, $fields, $where, $options = array())
    {
        if ($table === 'settinggroups') {
            return new RotatingAdsTestQuery($this->group);
        }

        preg_match("/name='([^']+)'/", $where, $matches);
        $name = isset($matches[1]) ? stripslashes($matches[1]) : '';

        return new RotatingAdsTestQuery(isset($this->settings[$name]) ? $this->settings[$name] : array());
    }

    public function fetch_array($query)
    {
        return $query->row;
    }

    public function insert_query($table, $values)
    {
        if ($table === 'settinggroups') {
            $values['gid'] = 12;
            $this->group = $values;
            return 12;
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
    public $rotating_ads_enable_css = 'Load plugin CSS';
    public $rotating_ads_enable_css_description = 'CSS description';
    public $rotating_ads_open_new_tab = 'Open ads in a new tab';
    public $rotating_ads_open_new_tab_description = 'New tab description';

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
        'rotating_ads_enable_css' => '1',
        'rotating_ads_open_new_tab' => '1',
    ),
);

require dirname(__DIR__) . '/Upload/inc/plugins/rotating_ads.php';

rotating_ads_test_assert(
    isset($plugins->hooks['global_start']) && $plugins->hooks['global_start'] === 'rotating_ads_build_output',
    'plugin should register the global_start hook'
);

$info = rotating_ads_info();
rotating_ads_test_assert(
    $info['name'] === 'Rotating Ads' && $info['version'] === '1.0.0',
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

rotating_ads_ensure_settings();
rotating_ads_test_assert(
    isset($db->settings['rotating_ads_sponsor_label'])
    && isset($db->settings['rotating_ads_enable_css'])
    && isset($db->settings['rotating_ads_open_new_tab']),
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
    strpos($rotating_ads_assets, 'https://static.example.com/css/rotating-ads.css?ver=100') !== false
    && strpos($rotating_ads_square, 'rotating-ad--square') !== false
    && $rotating_ads_banner === '',
    'global output should build assets and populated slots'
);

$mybb->settings['rotating_ads_enable_css'] = '0';
rotating_ads_build_output();
rotating_ads_test_assert(
    $rotating_ads_assets === '',
    'CSS loading should be configurable'
);

echo "Rotating Ads tests passed.\n";
