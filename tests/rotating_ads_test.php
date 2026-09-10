<?php

define('IN_MYBB', 1);
define('IN_ADMINCP', 1);
define('MYBB_ROOT', __DIR__ . '/../');
define('TIME_NOW', 1788912000);
define('TABLE_PREFIX', 'mybb_');

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
    public $type = 'mysqli';
    public $group = array();
    public $settings = array();
    public $ads = array();
    public $ads_table_exists = false;
    public $themes = array(
        array('tid' => 2),
        array('tid' => 3),
    );
    public $stylesheets = array();
    private $next_setting_id = 1;
    private $next_stylesheet_id = 1;
    private $next_ad_id = 1;

    public function simple_select($table, $fields, $where = '', $options = array())
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

        if ($table === 'rotating_ads') {
            $rows = array_values($this->ads);
            if (preg_match("/slot='([^']+)'/", $where, $matches)) {
                $slot = stripslashes($matches[1]);
                $rows = array_values(array_filter($rows, function ($ad) use ($slot, $where) {
                    return $ad['slot'] === $slot
                        && (strpos($where, "enabled='1'") === false || !empty($ad['enabled']));
                }));
            } elseif (preg_match("/aid='([0-9]+)'/", $where, $matches)) {
                $aid = (int)$matches[1];
                $rows = isset($this->ads[$aid]) ? array($this->ads[$aid]) : array();
            }

            usort($rows, function ($left, $right) {
                return array($left['slot'], $left['aid']) <=> array($right['slot'], $right['aid']);
            });

            return new RotatingAdsTestQuery($rows);
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

    public function fetch_field($query, $field)
    {
        $row = $query->next();
        return isset($row[$field]) ? $row[$field] : null;
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

        if ($table === 'rotating_ads') {
            $values = array_merge(array(
                'weight' => 1,
                'start_at' => 0,
                'end_at' => 0,
                'max_impressions' => 0,
                'max_clicks' => 0,
                'impressions' => 0,
                'clicks' => 0,
                'country_mode' => 'all',
                'country_codes' => '',
            ), $values);
            $values['aid'] = $this->next_ad_id++;
            $this->ads[$values['aid']] = $values;
            return $values['aid'];
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

        if ($table === 'rotating_ads') {
            preg_match("/aid='([0-9]+)'/", $where, $matches);
            $aid = isset($matches[1]) ? (int)$matches[1] : 0;
            if (isset($this->ads[$aid])) {
                $this->ads[$aid] = array_merge($this->ads[$aid], $values);
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

        if ($table === 'rotating_ads') {
            preg_match("/aid='([0-9]+)'/", $where, $matches);
            $aid = isset($matches[1]) ? (int)$matches[1] : 0;
            unset($this->ads[$aid]);
        }
    }

    public function escape_string($value)
    {
        return addslashes($value);
    }

    public function table_exists($table)
    {
        return $table === 'rotating_ads' && $this->ads_table_exists;
    }

    public function build_create_table_collation()
    {
        return '';
    }

    public function write_query($query)
    {
        if (strpos($query, 'mybb_rotating_ads') !== false) {
            $this->ads_table_exists = true;
        }

        if (preg_match("/SET (impressions|clicks)=\\1\+1 WHERE aid='([0-9]+)'/", $query, $matches)) {
            $metric = $matches[1];
            $aid = (int)$matches[2];
            $limit = $metric === 'clicks' ? 'max_clicks' : 'max_impressions';
            if (isset($this->ads[$aid])
                && (empty($this->ads[$aid][$limit]) || (int)$this->ads[$aid][$metric] < (int)$this->ads[$aid][$limit])) {
                $this->ads[$aid][$metric]++;
            }
        }
    }

    public function drop_table($table)
    {
        if ($table === 'rotating_ads') {
            $this->ads_table_exists = false;
            $this->ads = array();
        }
    }
}

class RotatingAdsTestLang
{
    public $rotating_ads_name = 'Rotating Ads';
    public $rotating_ads_description = 'Description';
    public $rotating_ads_settings_description = 'Settings description';
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

function change_admin_permission($module, $permission, $value = 1)
{
}

$plugins = new RotatingAdsTestPlugins();
$db = new RotatingAdsTestDatabase();
$lang = new RotatingAdsTestLang();
$mybb = (object)array(
    'asset_url' => 'https://static.example.com',
    'settings' => array(
        'bburl' => 'https://example.com/forum',
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
rotating_ads_test_assert(
    isset($plugins->hooks['misc_start']) && $plugins->hooks['misc_start'] === 'rotating_ads_track_request',
    'plugin should register its lightweight image and click tracking endpoint'
);
rotating_ads_test_assert(
    isset($plugins->hooks['admin_config_menu'])
    && $plugins->hooks['admin_config_menu'] === 'rotating_ads_admin_menu'
    && isset($plugins->hooks['admin_config_action_handler'])
    && $plugins->hooks['admin_config_action_handler'] === 'rotating_ads_admin_action_handler',
    'plugin should register a native Configuration management page'
);
$admin_actions = array();
rotating_ads_admin_action_handler($admin_actions);
rotating_ads_test_assert(
    isset($admin_actions['rotating_ads'])
    && $admin_actions['rotating_ads']['active'] === 'rotating_ads'
    && $admin_actions['rotating_ads']['file'] === 'settings.php',
    'native Admin CP route should resolve through the Configuration module'
);

$info = rotating_ads_info();
rotating_ads_test_assert(
    $info['name'] === 'Rotating Ads' && $info['version'] === '0.8.3',
    'plugin info should expose localized metadata and version'
);
rotating_ads_test_assert(
    strpos(file_get_contents(dirname(__DIR__) . '/Upload/inc/plugins/rotating_ads.php'), "rotating_ads_save_changes', 'Save changes'") !== false,
    'native edit form should provide its own non-empty submit label'
);

$ads = array(array(
    'image_url' => 'https://example.com/ad.jpg',
    'destination_url' => 'https://example.com/',
    'alt_text' => 'Example <Ad>',
    'weight' => 1
));
rotating_ads_test_assert(
    rotating_ads_valid_url('/images/sponsored/ad-hostpro.jpg')
    && rotating_ads_valid_url('https://cdn.example.com/ad.jpg')
    && !rotating_ads_valid_url('//cdn.example.com/ad.jpg')
    && !rotating_ads_valid_url('/images\\ad.jpg')
    && !rotating_ads_valid_url('javascript:alert(1)'),
    'URL validation should allow safe site-relative paths without allowing protocol-relative or unsafe URLs'
);

$local_ad = array(array(
    'aid' => 99,
    'image_url' => '/images/sponsored/ad-hostpro.jpg',
    'destination_url' => '/sponsors.php',
    'alt_text' => 'Local sponsor',
    'weight' => 1
));
$local_rendered = rotating_ads_render_slot('square', $local_ad, 'Sponsored', false);
rotating_ads_test_assert(
    strpos($local_rendered, 'action=ra_click&amp;to=example.com&amp;aid=99') !== false
    && strpos($local_rendered, 'action=rotating_ads_image&amp;aid=99') !== false,
    'site-relative ads should retain click and impression tracking'
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

$rotating_ads_multi_ads = array(
    array('image_url' => 'https://example.com/ad-1.jpg', 'destination_url' => 'https://example.com/1', 'alt_text' => 'One', 'weight' => 1),
    array('image_url' => 'https://example.com/ad-2.jpg', 'destination_url' => 'https://example.com/2', 'alt_text' => 'Two', 'weight' => 1)
);
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
rotating_ads_test_assert(
    rotating_ads_normalize_country_codes('us, ca US invalid g7') === 'US,CA',
    'country code normalization should keep unique ISO-style two-letter codes'
);
rotating_ads_test_assert(
    rotating_ads_country_code(array('HTTP_CF_IPCOUNTRY' => 'us')) === 'US'
    && rotating_ads_country_code(array('GEOIP_COUNTRY_CODE' => 'ca')) === 'CA',
    'country detection should support common trusted server headers'
);

$campaign = array(
    'enabled' => 1,
    'start_at' => TIME_NOW - 60,
    'end_at' => TIME_NOW + 60,
    'max_impressions' => 10,
    'impressions' => 9,
    'max_clicks' => 2,
    'clicks' => 1,
    'country_mode' => 'allow',
    'country_codes' => 'US,CA'
);
rotating_ads_test_assert(
    rotating_ads_ad_is_eligible($campaign, TIME_NOW, 'US')
    && !rotating_ads_ad_is_eligible($campaign, TIME_NOW, 'GB'),
    'country allowlists should include only matching visitors'
);
$campaign['country_mode'] = 'block';
rotating_ads_test_assert(
    !rotating_ads_ad_is_eligible($campaign, TIME_NOW, 'CA')
    && rotating_ads_ad_is_eligible($campaign, TIME_NOW, ''),
    'country blocklists should exclude matches and allow visitors with unknown countries'
);
$campaign['country_mode'] = 'all';
$campaign['impressions'] = 10;
rotating_ads_test_assert(
    !rotating_ads_ad_is_eligible($campaign, TIME_NOW, 'US'),
    'ads should stop being eligible at their impression limit'
);
rotating_ads_test_assert(
    rotating_ads_parse_admin_date('2026-09-10') === 1788998400
    && rotating_ads_parse_admin_date('2026-09-10', true) === 1789084799
    && rotating_ads_parse_admin_date('2026-02-30') === 0,
    'campaign dates should parse strictly in UTC with inclusive end dates'
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

rotating_ads_ensure_storage();
rotating_ads_ensure_settings();
$db->insert_query('rotating_ads', rotating_ads_prepare_ad_for_database(array(
    'slot' => 'square',
    'image_url' => 'https://custom.example/ad.png',
    'destination_url' => 'https://custom.example/',
    'alt_text' => 'Custom',
    'enabled' => 1
)));
rotating_ads_test_assert(
    isset($db->settings['rotating_ads_sponsor_label'])
    && isset($db->settings['rotating_ads_template_variables'])
    && isset($db->settings['rotating_ads_hidden_groups'])
    && isset($db->settings['rotating_ads_enable_css'])
    && isset($db->settings['rotating_ads_open_new_tab'])
    && isset($db->settings['rotating_ads_enable_rotation'])
    && isset($db->settings['rotating_ads_rotation_min_seconds'])
    && isset($db->settings['rotating_ads_rotation_max_seconds']),
    'setting synchronization should create polish settings'
);
rotating_ads_test_assert(
    strpos($db->settings['rotating_ads_template_variables']['optionscode'], "php\n") === 0
    && strpos($db->settings['rotating_ads_template_variables']['optionscode'], '{&#36;rotating_ads_square}') !== false
    && strpos($db->settings['rotating_ads_template_variables']['optionscode'], '{&#36;rotating_ads_banner}') !== false,
    'settings should render both template variables as native read-only guidance'
);
rotating_ads_test_assert(
    count($db->ads) === 1
    && $db->ads[1]['slot'] === 'square'
    && $db->ads[1]['weight'] === 1,
    'current ad records should use campaign defaults'
);

rotating_ads_build_output();
rotating_ads_test_assert(
    $rotating_ads_assets === ''
    && strpos($rotating_ads_square, 'rotating-ad--square') !== false
    && $rotating_ads_banner === '',
    'global output should build populated database-backed slots'
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
$db->insert_query('rotating_ads', rotating_ads_prepare_ad_for_database(array(
    'slot' => 'square',
    'image_url' => 'https://example.com/ad-2.jpg',
    'destination_url' => 'https://example.com/2',
    'alt_text' => 'Two',
    'enabled' => 1,
    'weight' => 2
)));
rotating_ads_build_output();
rotating_ads_test_assert(
    strpos($rotating_ads_assets, '/jscripts/rotating-ads.js?ver=080') !== false
    && strpos($rotating_ads_square, 'data-rotating-ads="1"') !== false
    && strpos($rotating_ads_square, 'data-rotating-ads-min="4"') !== false
    && strpos($rotating_ads_square, 'data-rotating-ads-max="8"') !== false
    && strpos($rotating_ads_square, 'action=ra_click&amp;to=custom.example&amp;aid=') !== false
    && substr_count($rotating_ads_square, 'action=rotating_ads_image&amp;aid=') === 2
    && substr_count($rotating_ads_square, ' src="https://example.com/forum/misc.php?action=rotating_ads_image') === 1
    && substr_count($rotating_ads_square, ' data-src="https://example.com/forum/misc.php?action=rotating_ads_image') === 1,
    'global output should add the rotation script and timing data when in-page rotation is enabled'
);

$metric_aid = array_key_first($db->ads);
$before_impressions = (int)$db->ads[$metric_aid]['impressions'];
rotating_ads_increment_metric($metric_aid, 'impressions');
rotating_ads_increment_metric($metric_aid, 'clicks');
rotating_ads_test_assert(
    (int)$db->ads[$metric_aid]['impressions'] === $before_impressions + 1
    && (int)$db->ads[$metric_aid]['clicks'] === 1,
    'tracked image and click requests should increment aggregate metrics'
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
    'users outside hidden groups should still receive ads'
);

echo "Rotating Ads tests passed.\n";
