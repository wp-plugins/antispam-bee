<?php
/*
Plugin Name: Antispam Bee
Text Domain: antispam_bee
Domain Path: /lang
Description: Easy and extremely productive spam-fighting plugin with many sophisticated solutions. Includes protection again trackback spam.
Author: Sergej M&uuml;ller
Author URI: http://www.wpSEO.org
Plugin URI: http://antispambee.com
Version: 1.9
*/


if (!function_exists ('is_admin')) {
header('Status: 403 Forbidden');
header('HTTP/1.1 403 Forbidden');
exit();
}
class Antispam_Bee {
var $base_name;
var $md5_sign;
var $spam_reason;
function Antispam_Bee() {
if ((defined('DOING_AJAX') && DOING_AJAX) or (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
return;
}
$this->base_name = plugin_basename(__FILE__);
$this->md5_sign = 'comment-' .substr(md5(get_bloginfo('url')), 0, 5);
if (defined('DOING_CRON')) {
add_action(
'antispam_bee_daily_cronjob',
array(
$this,
'exe_daily_cronjob'
)
);
} elseif (is_admin()) {
add_action(
'admin_menu',
array(
$this,
'init_admin_menu'
)
);
if ($this->is_current_page('home')) {
add_action(
'init',
array(
$this,
'load_plugin_lang'
)
);
add_action(
'admin_init',
array(
$this,
'add_plugin_sources'
)
);
} else if ($this->is_current_page('index')) {
add_action(
'init',
array(
$this,
'load_plugin_lang'
)
);
if ($this->get_option('dashboard_count')) {
if ($this->is_min_wp('3.0')) {
add_action(
'right_now_discussion_table_end',
array(
$this,
'add_discussion_table_end'
)
);
} else {
add_action(
'right_now_table_end',
array(
$this,
'add_table_end'
)
);
}
}
if ($this->get_option('dashboard_chart')) {
add_action(
'wp_dashboard_setup',
array(
$this,
'init_dashboard_chart'
)
);
}
} else if ($this->is_current_page('plugins')) {
add_action(
'init',
array(
$this,
'load_plugin_lang'
)
);
add_action(
'activate_' .$this->base_name,
array(
$this,
'init_plugin_options'
)
);
add_action(
'deactivate_' .$this->base_name,
array(
$this,
'clear_scheduled_hook'
)
);
add_action(
'admin_notices',
array(
$this,
'show_version_notice'
)
);
if ($this->is_min_wp('2.8')) {
add_filter(
'plugin_row_meta',
array(
$this,
'init_row_meta'
),
10,
2
);
} else {
add_filter(
'plugin_action_links',
array(
$this,
'init_action_links'
),
10,
2
);
}
}
} else {
add_action(
'template_redirect',
array(
$this,
'replace_comment_field'
)
);
add_action(
'init',
array(
$this,
'precheck_comment_request'
)
);
add_action(
'preprocess_comment',
array(
$this,
'verify_comment_request'
),
1
);
add_action(
'antispam_bee_count',
array(
$this,
'the_spam_count'
)
);
add_filter(
'comment_notification_text',
array(
$this,
'replace_whois_link'
)
);
add_filter(
'comment_moderation_text',
array(
$this,
'replace_whois_link'
)
);
}
}
function load_plugin_lang() {
load_plugin_textdomain(
'antispam_bee',
false,
'antispam-bee/lang'
);
}
function init_action_links($links, $file) {
if ($this->base_name == $file) {
return array_merge(
array(
sprintf(
'<a href="options-general.php?page=%s">%s</a>',
$this->base_name,
__('Settings')
)
),
$links
);
}
return $links;
}
function init_row_meta($links, $file) {
if ($this->base_name == $file) {
return array_merge(
$links,
array(
sprintf(
'<a href="options-general.php?page=%s">%s</a>',
$this->base_name,
__('Settings')
)
)
);
}
return $links;
}
function init_plugin_options() {
add_option(
'antispam_bee',
array(),
'',
'no'
);
$this->migrate_old_options();
if ($this->get_option('cronjob_enable')) {
$this->init_scheduled_hook();
}
}
function get_option($field) {
if (!$options = wp_cache_get('antispam_bee')) {
$options = get_option('antispam_bee');
wp_cache_set(
'antispam_bee',
$options
);
}
return @$options[$field];
}
function update_option($field, $value) {
$this->update_options(
array(
$field => $value
)
);
}
function update_options($data) {
$options = array_merge(
(array)get_option('antispam_bee'),
$data
);
update_option(
'antispam_bee',
$options
);
wp_cache_set(
'antispam_bee',
$options
);
}
function migrate_old_options() {
if (get_option('antispam_bee_cronjob_timestamp') === false) {
return;
}
$fields = array(
'flag_spam',
'ignore_pings',
'ignore_filter',
'ignore_type',
'no_notice',
'cronjob_enable',
'cronjob_interval',
'cronjob_timestamp',
'spam_count',
'dashboard_count'
);
foreach($fields as $field) {
$this->update_option(
$field,
get_option('antispam_bee_' .$field)
);
}
$GLOBALS['wpdb']->query("DELETE FROM `" .$GLOBALS['wpdb']->options. "` WHERE option_name LIKE 'antispam_bee_%'");
$GLOBALS['wpdb']->query("OPTIMIZE TABLE `" .$GLOBALS['wpdb']->options. "`");
}
function init_scheduled_hook() {
if (!wp_next_scheduled('antispam_bee_daily_cronjob')) {
wp_schedule_event(
time(),
'daily',
'antispam_bee_daily_cronjob'
);
}
}
function clear_scheduled_hook() {
if (wp_next_scheduled('antispam_bee_daily_cronjob')) {
wp_clear_scheduled_hook('antispam_bee_daily_cronjob');
}
}
function exe_daily_cronjob() {
if (!$this->get_option('cronjob_enable')) {
return;
}
$this->update_option(
'cronjob_timestamp',
time()
);
$this->delete_spam_comments();
}
function delete_spam_comments() {
$days = intval($this->get_option('cronjob_interval'));
if (empty($days)) {
return false;
}
$GLOBALS['wpdb']->query(
sprintf(
"DELETE FROM %s WHERE comment_approved = 'spam' AND SUBDATE(NOW(), %s) > comment_date_gmt",
$GLOBALS['wpdb']->comments,
$days
)
);
$GLOBALS['wpdb']->query("OPTIMIZE TABLE `" .$GLOBALS['wpdb']->comments. "`");
}
function init_admin_menu() {
$page = add_options_page(
'Antispam Bee',
'<img src="' .plugins_url('antispam-bee/img/icon.png'). '" id="ab_icon" alt="Antispam Bee" />Antispam Bee',
($this->is_min_wp('2.8') ? 'manage_options' : 9),
__FILE__,
array(
$this,
'show_admin_menu'
)
);
add_action(
'admin_print_scripts-' . $page,
array(
$this,
'add_enqueue_script'
)
);
add_action(
'admin_print_styles-' . $page,
array(
$this,
'add_enqueue_style'
)
);
}
function add_plugin_sources() {
$data = get_plugin_data(__FILE__);
wp_register_script(
'ab_script',
plugins_url('antispam-bee/js/script.js'),
array('jquery'),
$data['Version']
);
wp_register_style(
'ab_style',
plugins_url('antispam-bee/css/style.css'),
array(),
$data['Version']
);
}
function add_enqueue_script() {
wp_enqueue_script('ab_script');
}
function add_enqueue_style() {
wp_enqueue_style('ab_style');
}
function is_min_wp($version) {
return version_compare(
$GLOBALS['wp_version'],
$version. 'alpha',
'>='
);
}
function is_wp_touch() {
return strpos(TEMPLATEPATH, 'wptouch');
}
function is_min_php($version) {
return version_compare(
phpversion(),
$version,
'>='
);
}
function is_current_page($page) {
switch($page) {
case 'home':
return (!empty($_REQUEST['page']) && $_REQUEST['page'] == $this->base_name);
case 'index':
case 'plugins':
return (!empty($GLOBALS['pagenow']) && $GLOBALS['pagenow'] == sprintf('%s.php', $page));
default:
return false;
}
}
function check_user_can() {
if (current_user_can('manage_options') === false or current_user_can('edit_plugins') === false or !is_user_logged_in()) {
wp_die('You do not have permission to access!');
}
}
function add_table_end() {
echo sprintf(
'<tr>
<td class="first b b-tags"></td>
<td class="t tags"></td>
<td class="b b-spam" style="font-size:18px">%s</td>
<td class="last t">%s</td>
</tr>',
$this->get_spam_count(),
__('Blocked', 'antispam_bee')
);
}
function add_discussion_table_end() {
echo sprintf(
'<tr>
<td class="b b-spam" style="font-size:18px">%s</td>
<td class="last t">%s</td>
</tr>',
$this->get_spam_count(),
__('Blocked', 'antispam_bee')
);
}
function init_dashboard_chart() {
if ( !current_user_can('administrator') or !$this->is_min_php('5.0.2') or !empty($GLOBALS['is_IE']) ) {
return false;
}
wp_add_dashboard_widget(
'ab_spam_chart',
__('Antispam Bee Stats', 'antispam_bee'),
array(
$this,
'show_spam_chart'
)
);
add_action(
'wp_print_scripts',
array(
$this,
'add_dashboard_js'
)
);
add_action(
'admin_head',
array(
$this,
'add_dashboard_css'
)
);
}
function add_dashboard_css() {
wp_register_style(
'ab_chart',
plugins_url('antispam-bee/css/dashboard.css')
);
wp_print_styles('ab_chart');
}
function add_dashboard_js() {
$data = get_plugin_data(__FILE__);
wp_register_script(
'ab_chart',
plugins_url('antispam-bee/js/dashboard.js'),
array('jquery'),
$data['Version']
);
$stats = (array)$this->get_option('daily_stats');
$today = (int)strtotime('today');
$fields = array();
$i = 30;
krsort($stats, SORT_NUMERIC);
$stats = array_slice(
$stats,
(array_key_exists($today, $stats) ? 1 : 0),
30,
true
);
while ($i > 0) {
$day = strtotime('today -' .$i --. ' days');
$fields[$day] = (array_key_exists($day, $stats) ? $stats[$day] : 0);
}
wp_enqueue_script('ab_chart');
wp_localize_script(
'ab_chart',
'ab_chart',
array(
'entries' => sprintf(
'%s',
implode(',', array_values($fields))
)
)
);
}
function show_spam_chart() {
echo sprintf(
'<p class="sub">%s</p>
<canvas id="canvas" width="360" height="80">
<p>%s</p>
</canvas>',
__('Last 30 Days', 'antispam_bee'),
__('No HTML5 canvas support.', 'antispam_bee')
);
}
function show_version_notice() {
if ( $this->is_min_wp('2.7') ) {
return;
}
echo sprintf(
'<div class="error"><p><strong>Antispam Bee</strong> %s</p></div>',
__('requires at least WordPress 2.7', 'antispam_bee')
);
}
function show_plugin_info() {
$data = get_plugin_data(__FILE__);
echo sprintf(
'Antispam Bee %s %s <a href="http://eBiene.de" target="_blank">Sergej M&uuml;ller</a> | <a href="http://twitter.com/wpSEO" target="_blank">%s</a> | <a href="http://www.wpSEO.%s/?utm_source=antispambee&utm_medium=plugin&utm_campaign=plugins" target="_blank">%s</a>',
$data['Version'],
__('by', 'antispam_bee'),
__('Follow on Twitter', 'antispam_bee'),
(get_locale() == 'de_DE' ? 'de' : 'org'),
__('Learn about wpSEO', 'antispam_bee')
);
}
function cut_ip_address($ip) {
if (!empty($ip)) {
return str_replace(
strrchr($ip, '.'),
'',
$ip
);
}
}
function replace_comment_field() {
if (is_feed() or is_trackback() or $this->is_wp_touch()) {
return;
}
if (!is_singular() && !$this->get_option('always_allowed')) {
return;
}
ob_start(
create_function(
'$input',
'return preg_replace("#<textarea(.*?)name=([\"\'])comment([\"\'])(.+?)</textarea>#s", "<textarea$1name=$2' .$this->md5_sign. '$3$4</textarea><textarea name=\"comment\" rows=\"1\" cols=\"1\" style=\"display:none\"></textarea>", $input, 1);'
)
);
}
function check_country_code($ip) {
$key = $this->get_option('ipinfodb_key');
if (empty($ip) or empty($key)) {
return false;
}
$white = preg_split(
'/ /',
$this->get_option('country_white'),
-1,
PREG_SPLIT_NO_EMPTY
);
$black = preg_split(
'/ /',
$this->get_option('country_black'),
-1,
PREG_SPLIT_NO_EMPTY
);
if (empty($white) && empty($black)) {
return true;
}
$response = wp_remote_get(
sprintf(
'http://api.ipinfodb.com/v2/ip_query_country.php?key=%s&ip=%s',
$key,
$ip
)
);
if (is_wp_error($response)) {
return true;
}
preg_match(
'#Code>([A-Z]{2})</Country#i',
wp_remote_retrieve_body($response),
$matches
);
if (empty($matches[1])) {
return false;
}
if (!empty($black)) {
return (in_array($matches[1], $black)) ? false : true;
}
return (in_array($matches[1], $white)) ? true : false;
}
function check_honey_pot($ip) {
$key = $this->get_option('honey_key');
if (empty($ip) or empty($key)) {
return false;
}
$host = sprintf(
'%s.%s.dnsbl.httpbl.org',
$key,
implode(
'.',
array_reverse(
explode(
'.',
$ip
)
)
)
);
$bits = explode(
'.',
gethostbyname($host)
);
return !($bits[0] == 127 && $bits[3] & 4);
}
function precheck_comment_request() {
if (is_feed() or is_trackback() or $this->is_wp_touch()) {
return;
}
$request_url = @$_SERVER['REQUEST_URI'];
$hidden_field = @$_POST['comment'];
$plugin_field = @$_POST[$this->md5_sign];
if (empty($_POST) or empty($request_url) or strpos($request_url, 'wp-comments-post.php') === false) {
return;
}
if (empty($hidden_field) && !empty($plugin_field)) {
$_POST['comment'] = $plugin_field;
unset($_POST[$this->md5_sign]);
} else {
$_POST['bee_spam'] = 1;
}
}
function verify_comment_request($comment) {
$request_url = @$_SERVER['REQUEST_URI'];
$request_ip = @$_SERVER['REMOTE_ADDR'];
if (empty($request_url) or empty($request_ip)) {
return $this->flag_comment_request(
$comment,
'Empty Data'
);
}
$comment_type = @$comment['comment_type'];
$comment_url = @$comment['comment_author_url'];
$comment_body = @$comment['comment_content'];
$comment_email = @$comment['comment_author_email'];
$ping_types = array('pingback', 'trackback', 'pings');
$ping_allowed = !$this->get_option('ignore_pings');
if (!empty($comment_url)) {
$comment_parse = @parse_url($comment_url);
$comment_host = @$comment_parse['host'];
}
if (strpos($request_url, 'wp-comments-post.php') !== false && !empty($_POST)) {
if ($this->get_option('already_commented')) {
if ($GLOBALS['wpdb']->get_var("SELECT COUNT(comment_ID) FROM `" .$GLOBALS['wpdb']->comments. "` WHERE `comment_author_email` = '" .$comment_email. "' AND `comment_approved` = '1' LIMIT 1")) {
return $comment;
}
}
if (!empty($_POST['bee_spam'])) {
return $this->flag_comment_request(
$comment,
'CSS Hack'
);
}
if ($this->get_option('advanced_check')) {
if (strpos($request_ip, $this->cut_ip_address(gethostbyname(gethostbyaddr($request_ip)))) === false) {
return $this->flag_comment_request(
$comment,
'Server IP'
);
}
}
if ($this->get_option('country_code') && $this->check_country_code($request_ip) === false) {
return $this->flag_comment_request(
$comment,
'Country Check'
);
}
if ($this->get_option('honey_pot') && $this->check_honey_pot($request_ip) === false) {
return $this->flag_comment_request(
$comment,
'Honey Pot'
);
}
} else if (!empty($comment_type) && in_array($comment_type, $ping_types) && $ping_allowed) {
if (empty($comment_url) or empty($comment_body)) {
return $this->flag_comment_request(
$comment,
'Empty Data',
true
);
}
if (!empty($comment_host) && gethostbyname($comment_host) != $request_ip) {
return $this->flag_comment_request(
$comment,
'Server IP',
true
);
}
if ($this->get_option('country_code') && $this->check_country_code($request_ip) === false) {
return $this->flag_comment_request(
$comment,
'Country Check',
true
);
}
if ($this->get_option('honey_pot') && $this->check_honey_pot($request_ip) === false) {
return $this->flag_comment_request(
$comment,
'Honey Pot',
true
);
}
}
return $comment;
}
function flag_comment_request($comment, $reason, $is_ping = false) {
$spam_remove = !$this->get_option('flag_spam');
$spam_notice = !$this->get_option('no_notice');
$ignore_filter = $this->get_option('ignore_filter');
$ignore_type = $this->get_option('ignore_type');
$this->update_spam_count();
$this->update_daily_stats();
if ($spam_remove) {
die('Spam deleted.');
}
if ($ignore_filter && (($ignore_type == 1 && $is_ping) or ($ignore_type == 2 && !$is_ping))) {
die('Spam deleted.');
}
$this->spam_reason = $reason;
add_filter(
'pre_comment_approved',
create_function(
'',
'return "spam";'
)
);
add_filter(
'comment_post',
array(
$this,
'send_email_notify'
)
);
if ($spam_notice) {
$comment['comment_content'] = "[MARKED AS SPAM BY ANTISPAM BEE]\n" .$comment['comment_content'];
}
return $comment;
}
function replace_whois_link($body) {
if ($this->get_option('country_code')) {
return preg_replace(
'/^Whois .+?=(.+?)/m',
'IP Locator: http://ipinfodb.com/ip_locator.php?ip=$1',
$body
);
}
return $body;
}
function send_email_notify($id) {
if (!$this->get_option('email_notify')) {
return $id;
}
$comment = @$GLOBALS['commentdata'];
$ip = @$_SERVER['REMOTE_ADDR'];
if (empty($comment) or empty($ip)) {
return $id;
}
if (!$post = get_post($comment['comment_post_ID'])) {
return $id;
}
$this->load_plugin_lang();
$subject = sprintf(
'[%s] %s',
get_bloginfo('name'),
__('Comment marked as spam', 'antispam_bee')
);
$body = sprintf(
"%s \"%s\"\r\n\r\n",
__('New spam comment on your post', 'antispam_bee'),
$post->post_title
).sprintf(
"%s: %s (IP: %s)\r\n",
__('Author'),
$comment['comment_author'],
$ip
).sprintf(
__("%s: %s\r\n"),
__('URL'),
$comment['comment_author_url']
).sprintf(
"%s: http://ipinfodb.com/ip_locator.php?ip=%s\r\n",
__('IP Locator'),
$ip
).sprintf(
"%s: %s\r\n\r\n",
__('Spam Reason', 'antispam_bee'),
__($this->spam_reason, 'antispam_bee')
).sprintf(
"%s\r\n\r\n\r\n",
stripslashes(strip_tags($comment['comment_content']))
).(
EMPTY_TRASH_DAYS ? (
sprintf(
"%s: %s\r\n",
__('Trash it', 'antispam_bee'),
admin_url('comment.php?action=trash&c=' .$id)
)
) : (
sprintf(
"%s: %s\r\n",
__('Delete it', 'antispam_bee'),
admin_url('comment.php?action=delete&c=' .$id)
)
)
).sprintf(
"%s: %s\r\n",
__('Approve it', 'antispam_bee'),
admin_url('comment.php?action=approve&c=' .$id)
).sprintf(
"%s: %s\r\n\r\n",
__('Spam list', 'antispam_bee'),
admin_url('edit-comments.php?comment_status=spam')
).sprintf(
"%s\r\n%s\r\n",
__('Notify message by Antispam Bee', 'antispam_bee'),
__('http://antispambee.com', 'antispam_bee')
);
wp_mail(
get_bloginfo('admin_email'),
$subject,
$body
);
return $id;
}
function get_spam_count() {
$count = $this->get_option('spam_count');
return (get_locale() == 'de_DE' ? number_format($count, 0, '', '.') : number_format_i18n($count));
}
function the_spam_count() {
echo $this->get_spam_count();
}
function update_spam_count() {
$this->update_option(
'spam_count',
intval($this->get_option('spam_count') + 1)
);
}
function update_daily_stats() {
$stats = (array)$this->get_option('daily_stats');
$today = (int)strtotime('today');
if (array_key_exists($today, $stats)) {
$stats[$today] ++;
} else {
$stats[$today] = 1;
}
krsort($stats, SORT_NUMERIC);
$this->update_option(
'daily_stats',
array_slice($stats, 0, 31, true)
);
}
function show_help_link($anchor) {
if ( get_locale() != 'de_DE' ) {
return '';
}
echo sprintf(
'[<a href="http://playground.ebiene.de/1137/antispam-bee-wordpress-plugin/#%s" target="_blank">?</a>]',
$anchor
);
}
function show_admin_menu() {
if ( !$this->is_min_wp('2.8') ) {
$this->check_user_can();
}
if ( !empty($_POST) ) {
check_admin_referer('antispam_bee');
$options = array(
'flag_spam'=> (int)(!empty($_POST['antispam_bee_flag_spam'])),
'ignore_pings'=> (int)(!empty($_POST['antispam_bee_ignore_pings'])),
'ignore_filter'=> (int)(!empty($_POST['antispam_bee_ignore_filter'])),
'ignore_type'=> (int)(@$_POST['antispam_bee_ignore_type']),
'no_notice'=> (int)(!empty($_POST['antispam_bee_no_notice'])),
'email_notify'=> (int)(!empty($_POST['antispam_bee_email_notify'])),
'cronjob_enable'=> (int)(!empty($_POST['antispam_bee_cronjob_enable'])),
'cronjob_interval'=> (int)(@$_POST['antispam_bee_cronjob_interval']),
'dashboard_count'=> (int)(!empty($_POST['antispam_bee_dashboard_count'])),
'dashboard_chart'=> (int)(!empty($_POST['antispam_bee_dashboard_chart'])),
'advanced_check'=> (int)(!empty($_POST['antispam_bee_advanced_check'])),
'already_commented'=> (int)(!empty($_POST['antispam_bee_already_commented'])),
'always_allowed'=> (int)(!empty($_POST['antispam_bee_always_allowed'])),
'honey_pot'=> (int)(!empty($_POST['antispam_bee_honey_pot'])),
'honey_key'=> (string)(@$_POST['antispam_bee_honey_key']),
'country_code'=> (int)(!empty($_POST['antispam_bee_country_code'])),
'country_black'=> (string)(@$_POST['antispam_bee_country_black']),
'country_white'=> (string)(@$_POST['antispam_bee_country_white']),
'ipinfodb_key'=> (string)(@$_POST['antispam_bee_ipinfodb_key'])
);
if ( empty($options['cronjob_interval']) ) {
$options['cronjob_enable'] = 0;
}
if ( !empty($options['honey_key']) ) {
$options['honey_key'] = preg_replace(
'/[^a-z]/',
'',
strtolower(
strip_tags($options['honey_key'])
)
);
}
if ( empty($options['honey_key']) ) {
$options['honey_pot'] = 0;
}
if ( !empty($options['country_black']) ) {
$options['country_black'] = preg_replace(
'/[^A-Z ]/',
'',
strtoupper(
strip_tags($options['country_black'])
)
);
}
if ( !empty($options['country_white']) ) {
$options['country_white'] = preg_replace(
'/[^A-Z ]/',
'',
strtoupper(
strip_tags($options['country_white'])
)
);
}
if ( empty($options['ipinfodb_key']) ) {
$options['country_code'] = 0;
}
if ( empty($options['country_black']) && empty($options['country_white']) ) {
$options['country_code'] = 0;
}
if ( $options['cronjob_enable'] && !$this->get_option('cronjob_enable') ) {
$this->init_scheduled_hook();
} else if ( !$options['cronjob_enable'] && $this->get_option('cronjob_enable') ) {
$this->clear_scheduled_hook();
}
$this->update_options($options); ?>
<div id="message" class="updated fade">
<p>
<strong>
<?php _e('Settings saved.') ?>
</strong>
</p>
</div>
<?php } ?>
<div class="wrap">
<div class="icon32"></div>
<h2>
Antispam Bee
</h2>
<form method="post" action="">
<?php wp_nonce_field('antispam_bee') ?>
<div id="poststuff">
<div class="postbox">
<h3>
<?php _e('Settings') ?>
</h3>
<div class="inside">
<ul>
<li>
<div>
<input type="checkbox" name="antispam_bee_flag_spam" id="antispam_bee_flag_spam" value="1" <?php checked($this->get_option('flag_spam'), 1) ?> />
<label for="antispam_bee_flag_spam">
<?php _e('Mark as Spam, do not delete', 'antispam_bee') ?> <?php $this->show_help_link('flag_spam') ?>
</label>
</div>
<ul <?php echo ($this->get_option('flag_spam') ? '' : 'class="inact"') ?>>
<li>
<input type="checkbox" name="antispam_bee_ignore_filter" id="antispam_bee_ignore_filter" value="1" <?php checked($this->get_option('ignore_filter'), 1) ?> />
<?php _e('Limit on', 'antispam_bee') ?> <select name="antispam_bee_ignore_type"><?php foreach(array(1 => __('Comments'), 2 => __('Pings')) as $key => $value) {
echo '<option value="' .$key. '" ';
selected($this->get_option('ignore_type'), $key);
echo '>' .$value. '</option>';
} ?>
</select> <?php $this->show_help_link('ignore_filter') ?>
</li>
<li>
<input type="checkbox" name="antispam_bee_cronjob_enable" id="antispam_bee_cronjob_enable" value="1" <?php checked($this->get_option('cronjob_enable'), 1) ?> />
<?php echo sprintf(__('Spam will be automatically deleted after %s days', 'antispam_bee'), '<input type="text" name="antispam_bee_cronjob_interval" value="' .$this->get_option('cronjob_interval'). '" class="small-text" />') ?>&nbsp;<?php $this->show_help_link('cronjob_enable') ?>
<?php if ($this->get_option('cronjob_enable') && $this->get_option('cronjob_timestamp')) {
echo sprintf(
'&nbsp;(%s @ %s)',
__('Last check', 'antispam_bee'),
date_i18n('d.m.Y H:i:s', ($this->get_option('cronjob_timestamp') + get_option('gmt_offset') * 60))
);
} ?>
</li>
<li>
<input type="checkbox" name="antispam_bee_no_notice" id="antispam_bee_no_notice" value="1" <?php checked($this->get_option('no_notice'), 1) ?> />
<label for="antispam_bee_no_notice">
<?php _e('Hide the &quot;MARKED AS SPAM&quot; note', 'antispam_bee') ?> <?php $this->show_help_link('no_notice') ?>
</label>
</li>
<li>
<input type="checkbox" name="antispam_bee_email_notify" id="antispam_bee_email_notify" value="1" <?php checked($this->get_option('email_notify'), 1) ?> />
<label for="antispam_bee_email_notify">
<?php _e('Send an admin email when new spam item incoming', 'antispam_bee') ?> <?php $this->show_help_link('email_notify') ?>
</label>
</li>
</ul>
</li>
<li>
<div>
<input type="checkbox" name="antispam_bee_country_code" id="antispam_bee_country_code" value="1" <?php checked($this->get_option('country_code'), 1) ?> />
<label for="antispam_bee_country_code">
<?php _e('Block comments and pings from specific countries', 'antispam_bee') ?> <?php $this->show_help_link('country_code') ?>
</label>
</div>
<ul class="shift <?php echo ($this->get_option('country_code') ? '' : 'inact') ?>">
<li>
<label for="antispam_bee_country_black">
<?php _e('Blacklist', 'antispam_bee') ?> (<a href="http://www.iso.org/iso/english_country_names_and_code_elements" target="_blank"><?php _e('iso codes', 'antispam_bee') ?></a>)
</label>
<input type="text" name="antispam_bee_country_black" id="antispam_bee_country_black" value="<?php echo $this->get_option('country_black') ?>" class="regular-text code" />
</li>
<li>
&nbsp;
<br />
<?php _e('or', 'antispam_bee') ?>
</li>
<li>
<label for="antispam_bee_country_white">
<?php _e('Whitelist', 'antispam_bee') ?> (<a href="http://www.iso.org/iso/english_country_names_and_code_elements" target="_blank"><?php _e('iso codes', 'antispam_bee') ?></a>)
</label>
<input type="text" name="antispam_bee_country_white" id="antispam_bee_country_white" value="<?php echo $this->get_option('country_white') ?>" class="regular-text code" />
</li>
</ul>
<ul class="shift <?php echo ($this->get_option('country_code') ? '' : 'inact') ?>">
<li>
<label for="antispam_bee_ipinfodb_key">
IPInfoDB API Key (<a href="http://www.ipinfodb.com/register.php" target="_blank"><?php _e('get free', 'antispam_bee') ?></a>)
</label>
<input type="text" name="antispam_bee_ipinfodb_key" id="antispam_bee_ipinfodb_key" value="<?php echo $this->get_option('ipinfodb_key') ?>" class="maxi-text code" />
</li>
</ul>
</li>
<li>
<div>
<input type="checkbox" name="antispam_bee_honey_pot" id="antispam_bee_honey_pot" value="1" <?php checked($this->get_option('honey_pot'), 1) ?> />
<label for="antispam_bee_honey_pot">
<?php _e('Search comment spammers in the Project Honey Pot', 'antispam_bee') ?> <?php $this->show_help_link('honey_pot') ?>
</label>
</div>
<ul class="shift <?php echo ($this->get_option('honey_pot') ? '' : 'inact') ?>">
<li>
<label for="antispam_bee_honey_key">
Honey Pot API Key (<a href="http://www.projecthoneypot.org/httpbl_configure.php" target="_blank"><?php _e('get free', 'antispam_bee') ?></a>)
</label>
<input type="text" name="antispam_bee_honey_key" id="antispam_bee_honey_key" value="<?php echo $this->get_option('honey_key') ?>" class="maxi-text code" />
</li>
</ul>
</li>
<li>
<input type="checkbox" name="antispam_bee_ignore_pings" id="antispam_bee_ignore_pings" value="1" <?php checked($this->get_option('ignore_pings'), 1) ?> />
<label for="antispam_bee_ignore_pings">
<?php _e('Do not check trackbacks / pingbacks', 'antispam_bee') ?> <?php $this->show_help_link('ignore_pings') ?>
</label>
</li>
<li>
<input type="checkbox" name="antispam_bee_dashboard_count" id="antispam_bee_dashboard_count" value="1" <?php checked($this->get_option('dashboard_count'), 1) ?> />
<label for="antispam_bee_dashboard_count">
<?php _e('Display blocked comments count on the dashboard', 'antispam_bee') ?> <?php $this->show_help_link('dashboard_count') ?>
</label>
</li>
<?php if ( $this->is_min_php('5.0.2') ) { ?>
<li>
<input type="checkbox" name="antispam_bee_dashboard_chart" id="antispam_bee_dashboard_chart" value="1" <?php checked($this->get_option('dashboard_chart'), 1) ?> />
<label for="antispam_bee_dashboard_chart">
<?php _e('Display statistics on the dashboard (no IE support)', 'antispam_bee') ?> <?php $this->show_help_link('dashboard_chart') ?>
</label>
</li>
<?php } ?>
<li>
<input type="checkbox" name="antispam_bee_advanced_check" id="antispam_bee_advanced_check" value="1" <?php checked($this->get_option('advanced_check'), 1) ?> />
<label for="antispam_bee_advanced_check">
<?php _e('Enable stricter inspection for incomming comments', 'antispam_bee') ?> <?php $this->show_help_link('advanced_check') ?>
</label>
</li>
<li>
<input type="checkbox" name="antispam_bee_already_commented" id="antispam_bee_already_commented" value="1" <?php checked($this->get_option('already_commented'), 1) ?> />
<label for="antispam_bee_already_commented">
<?php _e('Do not check if the comment author has already approved', 'antispam_bee') ?> <?php $this->show_help_link('already_commented') ?>
</label>
</li>
<li>
<input type="checkbox" name="antispam_bee_always_allowed" id="antispam_bee_always_allowed" value="1" <?php checked($this->get_option('always_allowed'), 1) ?> />
<label for="antispam_bee_always_allowed">
<?php _e('Comments are also used outside of posts and pages', 'antispam_bee') ?> <?php $this->show_help_link('always_allowed') ?>
</label>
</li>
</ul>
<p>
<input type="submit" name="antispam_bee_submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>
</div>
</div>
<div class="postbox">
<h3>
<?php _e('About', 'antispam_bee') ?>
</h3>
<div class="inside">
<p>
<?php $this->show_plugin_info() ?>
</p>
</div>
</div>
</div>
</form>
</div>
<?php }
}
new Antispam_Bee();