<?php
/*
Plugin Name: Antispam Bee
Plugin URI: http://antispambee.com
Description: Antispam Bee is the easy and productive antispam plugin for WordPress. Trackback and pingback spam protection included.
Author: Sergej M&uuml;ller
Version: 1.6
Author URI: http://www.wpSEO.org
*/


if (!function_exists ('is_admin')) {
header('Status: 403 Forbidden');
header('HTTP/1.1 403 Forbidden');
exit();
}
class Antispam_Bee {
var $base_name;
var $dir_url;
var $md5_sign;
var $wp_lang;
function Antispam_Bee() {
$this->base_name = plugin_basename(__FILE__);
$this->dir_url = WP_PLUGIN_URL. '/' .dirname($this->base_name);
$this->md5_sign = 'comment-' .substr(md5(get_bloginfo('url')), 0, 5);
$this->wp_lang = get_locale();
if (is_admin()) {
add_action(
'init',
array(
$this,
'load_plugin_lang'
)
);
add_action(
'admin_menu',
array(
$this,
'init_admin_menu'
)
);
if ($this->is_current_page('home')) {
add_action(
'admin_init',
array(
$this,
'add_plugin_sources'
)
);
} else if ($this->is_current_page('index')) {
if ($this->get_plugin_option('dashboard_count')) {
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
} else if ($this->is_current_page('plugins')) {
if (!$this->is_min_wp('2.7')) {
add_action(
'admin_notices',
array(
$this,
'show_plugin_notices'
)
);
}
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
if ($this->get_plugin_option('cronjob_enable')) {
add_action(
'antispam_bee_daily_cronjob',
array(
$this,
'exe_daily_cronjob'
)
);
}
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
if ($this->get_plugin_option('cronjob_enable')) {
$this->init_scheduled_hook();
}
}
function init_scheduled_hook() {
if (function_exists('wp_schedule_event')) {
if (!wp_next_scheduled('antispam_bee_daily_cronjob')) {
wp_schedule_event(time(), 'daily', 'antispam_bee_daily_cronjob');
}
}
}
function clear_scheduled_hook() {
if (function_exists('wp_schedule_event')) {
if (wp_next_scheduled('antispam_bee_daily_cronjob')) {
wp_clear_scheduled_hook('antispam_bee_daily_cronjob');
}
}
}
function exe_daily_cronjob() {
$this->delete_spam_comments();
$this->set_plugin_option(
'cronjob_timestamp',
time()
);
}
function delete_spam_comments() {
$days = intval($this->get_plugin_option('cronjob_interval'));
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
function get_plugin_option($field) {
if (!$options = wp_cache_get('antispam_bee')) {
$options = get_option('antispam_bee');
wp_cache_set(
'antispam_bee',
$options
);
}
return @$options[$field];
}
function set_plugin_option($field, $value) {
if (empty($field)) {
return;
}
$this->set_plugin_options(
array(
$field => $value
)
);
}
function set_plugin_options($data) {
if (empty($data)) {
return;
}
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
$this->set_plugin_option(
$field,
get_option('antispam_bee_' .$field)
);
}
$GLOBALS['wpdb']->query("DELETE FROM `" .$GLOBALS['wpdb']->options. "` WHERE option_name LIKE 'antispam_bee_%'");
$GLOBALS['wpdb']->query("OPTIMIZE TABLE `" .$GLOBALS['wpdb']->options. "`");
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
function is_current_page($page) {
switch($page) {
case 'home':
return (isset($_REQUEST['page']) && $_REQUEST['page'] == $this->base_name);
case 'index':
case 'plugins':
return ($GLOBALS['pagenow'] == sprintf('%s.php', $page));
}
return false;
}
function get_admin_page($page) {
if (empty($page)) {
return;
}
if (function_exists('admin_url')) {
return admin_url($page);
}
return (get_option('siteurl'). '/wp-admin/' .$page);
}
function check_user_can() {
if (current_user_can('manage_options') === false || current_user_can('edit_plugins') === false || !is_user_logged_in()) {
wp_die('You do not have permission to access!');
}
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
function show_plugin_notices() {
echo sprintf(
'<div class="error"><p><strong>Antispam Bee</strong> %s</p></div>',
__('requires at least WordPress 2.7', 'antispam_bee')
);
}
function show_plugin_info() {
$data = get_plugin_data(__FILE__);
echo sprintf(
'%1$s: %2$s | %3$s: %4$s | %5$s: <a href="http://eBiene.de" target="_blank">Sergej Müller</a> | <a href="http://twitter.com/wpSEO" target="_blank">%6$s</a> | <a href="http://www.wpSEO.%7$s/?utm_source=antispambee&utm_medium=plugin&utm_campaign=plugins" target="_blank">%8$s</a><br />',
__('Plugin'),
'Antispam Bee',
__('Version'),
$data['Version'],
__('Author'),
__('Follow on Twitter', 'antispam_bee'),
($this->wp_lang == 'de_DE' ? 'de' : 'org'),
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
if (is_feed() || is_trackback() || $this->is_wp_touch()) {
return;
}
if (!is_singular() && !$this->get_plugin_option('always_allowed')) {
return;
}
ob_start(
create_function(
'$input',
'return preg_replace("#<textarea(.*?)name=([\"\'])comment([\"\'])(.+?)</textarea>#s", "<textarea$1name=$2' .$this->md5_sign. '$3$4</textarea><textarea name=\"comment\" rows=\"1\" cols=\"1\" style=\"display:none\"></textarea>", $input, 1);'
)
);
}
function precheck_comment_request() {
if (is_feed() || is_trackback() || $this->is_wp_touch()) {
return;
}
$request_url = @$_SERVER['REQUEST_URI'];
$hidden_field = @$_POST['comment'];
$plugin_field = @$_POST[$this->md5_sign];
if (empty($_POST) || empty($request_url) || strpos($request_url, 'wp-comments-post.php') === false) {
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
if (empty($request_url) || empty($request_ip)) {
return $this->flag_comment_request($comment);
}
$comment_type = @$comment['comment_type'];
$comment_url = @$comment['comment_author_url'];
$comment_body = @$comment['comment_content'];
$comment_email = @$comment['comment_author_email'];
$ping_types = array('pingback', 'trackback', 'pings');
$ping_allowed = !$this->get_plugin_option('ignore_pings');
if (!empty($comment_url)) {
$comment_parse = @parse_url($comment_url);
$comment_host = @$comment_parse['host'];
}
if (strpos($request_url, 'wp-comments-post.php') !== false && !empty($_POST)) {
if ($this->get_plugin_option('already_commented')) {
if ($GLOBALS['wpdb']->get_var("SELECT COUNT(comment_ID) FROM `" .$GLOBALS['wpdb']->comments. "` WHERE `comment_author_email` = '" .$comment_email. "' AND `comment_approved` = '1' LIMIT 1")) {
return $comment;
}
}
if (!empty($_POST['bee_spam'])) {
return $this->flag_comment_request($comment);
}
if ($this->get_plugin_option('advanced_check')) {
if (strpos($request_ip, $this->cut_ip_address(gethostbyname(gethostbyaddr($request_ip)))) === false) {
return $this->flag_comment_request($comment);
}
}
} else if (!empty($comment_type) && in_array($comment_type, $ping_types) && $ping_allowed) {
if (empty($comment_url) || empty($comment_body)) {
return $this->flag_comment_request($comment, true);
} else if (!empty($comment_host) && gethostbyname($comment_host) != $request_ip) {
return $this->flag_comment_request($comment, true);
}
}
return $comment;
}
function flag_comment_request($comment, $is_ping = false) {
$spam_remove = !$this->get_plugin_option('flag_spam');
$spam_notice = !$this->get_plugin_option('no_notice');
$ignore_filter = $this->get_plugin_option('ignore_filter');
$ignore_type = $this->get_plugin_option('ignore_type');
$this->update_spam_count();
if ($spam_remove) {
die('Spam deleted.');
}
if ($ignore_filter && (($ignore_type == 1 && $is_ping) || ($ignore_type == 2 && !$is_ping))) {
die('Spam deleted.');
}
add_filter(
'pre_comment_approved',
array(
$this,
'i_am_spam'
)
);
if ($spam_notice) {
$comment['comment_content'] = "[MARKED AS SPAM BY ANTISPAM BEE]\n" .$comment['comment_content'];
}
return $comment;
}
function i_am_spam($approved) {
if ($this->get_plugin_option('email_notify')) {
$this->send_email_notify($GLOBALS['commentdata']);
}
return 'spam';
}
function send_email_notify($comment) {
$email = get_bloginfo('admin_email');
$blog = get_bloginfo('name');
$body = @$comment['comment_content'];
if (empty($email) || empty($blog) || empty($body)) {
return;
}
$body = stripslashes(strip_tags($body));
$this->load_plugin_lang();
wp_mail(
$email,
sprintf(
'[%s] %s',
$blog,
__('Comment marked as spam', 'antispam_bee')
),
sprintf(
"%s\n\n%s: %s",
$body,
__('Spam list', 'antispam_bee'),
$this->get_admin_page('edit-comments.php?comment_status=spam')
)
);
}
function get_spam_count() {
return number_format_i18n(
$this->get_plugin_option('spam_count')
);
}
function the_spam_count() {
echo $this->get_spam_count();
}
function update_spam_count() {
$this->set_plugin_option(
'spam_count',
intval($this->get_plugin_option('spam_count') + 1)
);
}
function show_help_link($anchor) {
if ($this->wp_lang != 'de_DE') {
return '';
}
echo sprintf(
'[<a href="http://playground.ebiene.de/1137/antispam-bee-wordpress-plugin/#%s" target="_blank">?</a>]',
$anchor
);
}
function add_plugin_sources() {
$data = get_plugin_data(__FILE__);
wp_register_script(
'ab_script',
$this->dir_url. '/js/script.js',
array('jquery'),
$data['Version']
);
wp_register_style(
'ab_style',
$this->dir_url. '/css/style.css',
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
function show_admin_menu() {
$this->check_user_can();
if (!empty($_POST)) {
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
'advanced_check'=> (int)(!empty($_POST['antispam_bee_advanced_check'])),
'already_commented'=> (int)(!empty($_POST['antispam_bee_already_commented'])),
'always_allowed'=> (int)(!empty($_POST['antispam_bee_always_allowed']))
);
if (empty($options['cronjob_interval'])) {
$options['cronjob_enable'] = 0;
}
if ($options['cronjob_enable'] && !$this->get_plugin_option('cronjob_enable')) {
$this->init_scheduled_hook();
} else if (!$options['cronjob_enable'] && $this->get_plugin_option('cronjob_enable')) {
$this->clear_scheduled_hook();
}
$this->set_plugin_options($options); ?>
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
<table class="form-table">
<tr>
<td>
<label for="antispam_bee_flag_spam">
<input type="checkbox" name="antispam_bee_flag_spam" id="antispam_bee_flag_spam" value="1" <?php checked($this->get_plugin_option('flag_spam'), 1) ?> />
<?php _e('Mark as Spam, do not delete', 'antispam_bee') ?> <?php $this->show_help_link('flag_spam') ?>
</label>
</td>
</tr>
<tr>
<td class="shift">
<input type="checkbox" name="antispam_bee_ignore_filter" id="antispam_bee_ignore_filter" value="1" <?php checked($this->get_plugin_option('ignore_filter'), 1) ?> />
<?php _e('Limit on', 'antispam_bee') ?> <select name="antispam_bee_ignore_type"><?php foreach(array(1 => __('Comments'), 2 => __('Pings')) as $key => $value) {
echo '<option value="' .$key. '" ';
selected($this->get_plugin_option('ignore_type'), $key);
echo '>' .$value. '</option>';
} ?>
</select> <?php $this->show_help_link('ignore_filter') ?>
</td>
</tr>
<tr>
<td class="shift">
<input type="checkbox" name="antispam_bee_cronjob_enable" id="antispam_bee_cronjob_enable" value="1" <?php checked($this->get_plugin_option('cronjob_enable'), 1) ?> />
<?php echo sprintf(__('Spam will be automatically deleted after %s days', 'antispam_bee'), '<input type="text" name="antispam_bee_cronjob_interval" value="' .$this->get_plugin_option('cronjob_interval'). '" class="small-text" />') ?>&nbsp;<?php $this->show_help_link('cronjob_enable') ?>
<?php echo ($this->get_plugin_option('cronjob_timestamp') ? ('&nbsp;<span class="setting-description">(' .__('Last', 'antispam_bee'). ': '. date_i18n('d.m.Y H:i:s', $this->get_plugin_option('cronjob_timestamp')). ')</span>') : '') ?>
</td>
</tr>
<tr>
<td class="shift">
<label for="antispam_bee_no_notice">
<input type="checkbox" name="antispam_bee_no_notice" id="antispam_bee_no_notice" value="1" <?php checked($this->get_plugin_option('no_notice'), 1) ?> />
<?php _e('Hide the &quot;MARKED AS SPAM&quot; note', 'antispam_bee') ?> <?php $this->show_help_link('no_notice') ?>
</label>
</td>
</tr>
<tr>
<td class="shift">
<label for="antispam_bee_email_notify">
<input type="checkbox" name="antispam_bee_email_notify" id="antispam_bee_email_notify" value="1" <?php checked($this->get_plugin_option('email_notify'), 1) ?> />
<?php _e('Send an admin email when new spam item incoming', 'antispam_bee') ?> <?php $this->show_help_link('email_notify') ?>
</label>
</td>
</tr>
</table>
<table class="form-table">
<tr>
<td>
<label for="antispam_bee_ignore_pings">
<input type="checkbox" name="antispam_bee_ignore_pings" id="antispam_bee_ignore_pings" value="1" <?php checked($this->get_plugin_option('ignore_pings'), 1) ?> />
<?php _e('Do not check trackbacks / pingbacks', 'antispam_bee') ?> <?php $this->show_help_link('ignore_pings') ?>
</label>
</td>
</tr>
<tr>
<td>
<label for="antispam_bee_dashboard_count">
<input type="checkbox" name="antispam_bee_dashboard_count" id="antispam_bee_dashboard_count" value="1" <?php checked($this->get_plugin_option('dashboard_count'), 1) ?> />
<?php _e('Display blocked comments count on the dashboard', 'antispam_bee') ?> <?php $this->show_help_link('dashboard_count') ?>
</label>
</td>
</tr>
<tr>
<td>
<label for="antispam_bee_advanced_check">
<input type="checkbox" name="antispam_bee_advanced_check" id="antispam_bee_advanced_check" value="1" <?php checked($this->get_plugin_option('advanced_check'), 1) ?> />
<?php _e('Enable stricter inspection for incomming comments', 'antispam_bee') ?> <?php $this->show_help_link('advanced_check') ?>
</label>
</td>
</tr>
<tr>
<td>
<label for="antispam_bee_already_commented">
<input type="checkbox" name="antispam_bee_already_commented" id="antispam_bee_already_commented" value="1" <?php checked($this->get_plugin_option('already_commented'), 1) ?> />
<?php _e('Do not check if the author has already commented and approved', 'antispam_bee') ?> <?php $this->show_help_link('already_commented') ?>
</label>
</td>
</tr>
<tr>
<td>
<label for="antispam_bee_always_allowed">
<input type="checkbox" name="antispam_bee_always_allowed" id="antispam_bee_always_allowed" value="1" <?php checked($this->get_plugin_option('always_allowed'), 1) ?> />
<?php _e('Comments are also used outside of posts and pages', 'antispam_bee') ?> <?php $this->show_help_link('always_allowed') ?>
</label>
</td>
</tr>
</table>
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
$GLOBALS['Antispam_Bee'] = new Antispam_Bee();