<?php
/*
Plugin Name: Antispam Bee
Plugin URI: http://antispambee.com
Description: Antispam Bee is the easy and productive antispam plugin for WordPress. Trackback and pingback spam protection included.
Author: Sergej M&uuml;ller
Version: 1.1
Author URI: http://www.wpSEO.org
*/


if (!function_exists ('is_admin')) {
header('Status: 403 Forbidden');
header('HTTP/1.1 403 Forbidden');
exit();
}
class Antispam_Bee {
function Antispam_Bee() {
if (!defined('PLUGINDIR')) {
define('PLUGINDIR', 'wp-content/plugins');
}
$this->plugin_basename = plugin_basename(__FILE__);
$this->protect = 'comment-' .substr(md5(get_bloginfo('home')), 0, 5);
if (is_admin()) {
add_action(
'admin_menu',
array(
$this,
'init_admin_menu'
)
);
if ($this->is_current_page('home')) {
add_action(
'admin_head',
array(
$this,
'show_plugin_head'
)
);
load_plugin_textdomain(
'antispam_bee',
sprintf(
'%s/antispam-bee/lang',
PLUGINDIR
)
);
} else if ($this->is_current_page('plugins')) {
if (!$this->is_min_wp('2.1')) {
add_action(
'admin_notices',
array(
$this,
'show_plugin_notices'
)
);
}
add_action(
'activate_' .$this->plugin_basename,
array(
$this,
'init_plugin_options'
)
);
add_action(
'deactivate_' .$this->plugin_basename,
array(
$this,
'clear_cron_job'
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
'replace_comment_textarea'
),
1,
1
);
add_action(
'init',
array(
$this,
'precheck_comment_request'
),
1,
1
);
add_action(
'preprocess_comment',
array(
$this,
'check_comment_request'
),
1,
1
);
add_action(
'antispam_bee_daily_cronjob',
array(
$this,
'exe_daily_cronjob'
)
);
}
}
function init_action_links($links, $file) {
if ($this->plugin_basename == $file) {
return array_merge(
array(
sprintf(
'<a href="options-general.php?page=%s">%s</a>',
$this->plugin_basename,
__('Settings')
)
),
$links
);
}
return $links;
}
function init_row_meta($links, $file) {
if ($this->plugin_basename == $file) {
return array_merge(
$links,
array(
sprintf(
'<a href="options-general.php?page=%s">%s</a>',
$this->plugin_basename,
__('Settings')
)
)
);
}
return $links;
}
function init_plugin_options() {
$this->init_cron_job();
add_option('antispam_bee_flag_spam');
add_option('antispam_bee_ignore_pings');
add_option('antispam_bee_ignore_filter');
add_option('antispam_bee_ignore_type');
add_option('antispam_bee_no_notice');
add_option('antispam_bee_cronjob_enable');
add_option('antispam_bee_cronjob_interval');
add_option('antispam_bee_cronjob_timestamp');
}
function init_cron_job() {
if (function_exists('wp_schedule_event')) {
if (!wp_next_scheduled('antispam_bee_daily_cronjob')) {
wp_schedule_event(time(), 'daily', 'antispam_bee_daily_cronjob');
}
}
}
function clear_cron_job() {
if (function_exists('wp_schedule_event')) {
if (wp_next_scheduled('antispam_bee_daily_cronjob')) {
wp_clear_scheduled_hook('antispam_bee_daily_cronjob');
}
}
}
function init_admin_menu() {
add_options_page(
'Antispam Bee',
($this->is_min_wp('2.7') ? '<img src="' .plugins_url('antispam-bee/img/icon.png'). '" width="11" height="9" border="0" alt="Antispam Bee" />' : ''). 'Antispam Bee',
9,
__FILE__,
array(
$this,
'show_admin_menu'
)
);
}
function exe_daily_cronjob() {
if (!get_option('antispam_bee_cronjob_enable') || (get_option('antispam_bee_cronjob_timestamp') + (60 * 60) > time())) {
return;
}
update_option(
'antispam_bee_cronjob_timestamp',
time()
);
$this->delete_spam_comments(
get_option('antispam_bee_cronjob_interval')
);
}
function delete_spam_comments($days) {
$days = intval($days);
if (!$days) {
return false;
}
$GLOBALS['wpdb']->query(
sprintf(
"DELETE FROM %s WHERE comment_approved = 'spam' AND SUBDATE(NOW(), %s) > comment_date_gmt",
$GLOBALS['wpdb']->comments,
$days
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
function is_current_page($page) {
switch($page) {
case 'home':
return (isset($_REQUEST['page']) && $_REQUEST['page'] == $this->plugin_basename);
case 'index':
case 'plugins':
return ($GLOBALS['pagenow'] == sprintf('%s.php', $page));
}
return false;
}
function check_user_can() {
if (current_user_can('manage_options') === false || current_user_can('edit_plugins') === false || !is_user_logged_in()) {
wp_die('You do not have permission to access!');
}
}
function show_plugin_notices() {
load_plugin_textdomain(
'antispam_bee',
sprintf(
'%s/antispam-bee/lang',
PLUGINDIR
)
);
echo sprintf(
'<div class="error"><p><strong>Antispam Bee</strong> %s</p></div>',
__('requires at least WordPress 2.1', 'antispam_bee')
);
}
function show_plugin_info() {
$data = get_plugin_data(__FILE__);
echo sprintf(
'%1$s: %2$s | %3$s: %4$s | %5$s: %6$s<br />',
__('Plugin'),
'Antispam Bee',
__('Version'),
$data['Version'],
__('Author'),
$data['Author']
);
}
function show_plugin_head() {
wp_enqueue_script('jquery'); ?>
<style type="text/css">
<?php if ($this->is_min_wp('2.7')) { ?>
div.icon32 {
background: url(<?php echo plugins_url('antispam-bee/img/icon32.png') ?>) no-repeat;
}
div.inside {
background: url(<?php echo plugins_url('antispam-bee/img/icon270.png') ?>) no-repeat right 30px;
}
div.less {
background: none;
}
<?php } ?>
select {
margin: 0 0 -3px;
}
input.small-text {
margin: -5px 0;
}
td.shift {
padding-left: 30px;
}
</style>
<script type="text/javascript">
jQuery(document).ready(
function($) {
function manage_options() {
var id = 'antispam_bee_flag_spam';
$('#' + id).parents('.form-table').find('input[id!="' + id + '"]').attr('disabled', !$('#' + id).attr('checked'));
}
$('#antispam_bee_flag_spam').click(manage_options);
manage_options();
}
);
</script>
<?php }
function show_admin_menu() {
$this->check_user_can();
if (isset($_POST) && !empty($_POST)) {
check_admin_referer('antispam_bee');
$fields = array(
'antispam_bee_flag_spam',
'antispam_bee_ignore_pings',
'antispam_bee_ignore_filter',
'antispam_bee_ignore_type',
'antispam_bee_no_notice',
'antispam_bee_cronjob_enable',
'antispam_bee_cronjob_interval'
);
foreach($fields as $field) {
update_option(
$field,
intval(@$_POST[$field])
);
}
if (!get_option('antispam_bee_cronjob_interval')) {
update_option(
'antispam_bee_cronjob_enable',
''
);
}
?>
<div id="message" class="updated fade">
<p>
<strong>
<?php _e('Settings saved.') ?>
</strong>
</p>
</div>
<?php } ?>
<div class="wrap">
<?php if ($this->is_min_wp('2.7')) { ?>
<div class="icon32"><br /></div>
<?php } ?>
<h2>
Antispam Bee
</h2>
<form method="post" action="">
<?php wp_nonce_field('antispam_bee') ?>
<div id="poststuff" class="ui-sortable">
<div class="postbox">
<h3>
<?php _e('Settings') ?>
</h3>
<div class="inside">
<table class="form-table">
<tr>
<td>
<label for="antispam_bee_flag_spam">
<input type="checkbox" name="antispam_bee_flag_spam" id="antispam_bee_flag_spam" value="1" <?php checked(get_option('antispam_bee_flag_spam'), 1) ?> />
<?php _e('Mark as Spam, do not delete', 'antispam_bee') ?>
</label>
</td>
</tr>
<tr>
<td class="shift">
<input type="checkbox" name="antispam_bee_ignore_filter" id="antispam_bee_ignore_filter" value="1" <?php checked(get_option('antispam_bee_ignore_filter'), 1) ?> />
<?php _e('Limit on', 'antispam_bee') ?> <select name="antispam_bee_ignore_type"><?php foreach(array(1 => __('Comments'), 2 => __('Pings')) as $key => $value) {
echo '<option value="' .$key. '" ';
selected(get_option('antispam_bee_ignore_type'), $key);
echo '>' .$value. '</option>';
} ?>
</select>
</td>
</tr>
<tr>
<td class="shift">
<input type="checkbox" name="antispam_bee_cronjob_enable" id="antispam_bee_cronjob_enable" value="1" <?php checked(get_option('antispam_bee_cronjob_enable'), 1) ?> />
<?php echo sprintf(__('Spam will be automatically deleted after %s days', 'antispam_bee'), '<input type="text" name="antispam_bee_cronjob_interval" value="' .get_option('antispam_bee_cronjob_interval'). '" class="small-text" />') ?>
<?php echo (get_option('antispam_bee_cronjob_timestamp') ? ('&nbsp;<span class="setting-description">(' .__('Last', 'antispam_bee'). ': '. date_i18n('d.m.Y H:i:s', get_option('antispam_bee_cronjob_timestamp')). ')</span>') : '') ?>
</td>
</tr>
<tr>
<td class="shift">
<label for="antispam_bee_no_notice">
<input type="checkbox" name="antispam_bee_no_notice" id="antispam_bee_no_notice" value="1" <?php checked(get_option('antispam_bee_no_notice'), 1) ?> />
<?php _e('Hide the &quot;MARKED AS SPAM&quot; note', 'antispam_bee') ?>
</label>
</td>
</tr>
</table>
<table class="form-table">
<tr>
<td>
<label for="antispam_bee_ignore_pings">
<input type="checkbox" name="antispam_bee_ignore_pings" id="antispam_bee_ignore_pings" value="1" <?php checked(get_option('antispam_bee_ignore_pings'), 1) ?> />
<?php _e('Do not check trackbacks / pingbacks', 'antispam_bee') ?>
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
<div class="inside less">
<p>
<?php $this->show_plugin_info() ?>
</p>
</div>
</div>
</div>
</form>
</div>
<?php }
function replace_comment_textarea() {
if (is_singular() && strpos(TEMPLATEPATH, 'wptouch') === false) {
ob_start(
create_function(
'$input',
'return preg_replace("#<textarea(.*?)name=([\"\'])comment([\"\'])(.+?)</textarea>#s", "<textarea$1name=$2' .$this->protect. '$3$4</textarea><textarea name=\"comment\" rows=\"1\" cols=\"1\" style=\"display:none\"></textarea>", $input);'
)
);
}
}
function precheck_comment_request() {
if (strpos($_SERVER['REQUEST_URI'], 'wp-comments-post.php') !== false && isset($_POST) && !empty($_POST)) {
if (isset($_POST['comment']) && isset($_POST[$this->protect]) && empty($_POST['comment']) && !empty($_POST[$this->protect])) {
$_POST['comment'] = $_POST[$this->protect];
unset($_POST[$this->protect]);
} else {
$_POST['bee_spam'] = 1;
}
}
}
function check_comment_request($comment) {
if (strpos($_SERVER['REQUEST_URI'], 'wp-comments-post.php') !== false && isset($_POST) && !empty($_POST)) {
if (isset($_POST['bee_spam']) && !empty($_POST['bee_spam'])) {
return $this->mark_comment_request($comment);
}
} else if (!get_option('antispam_bee_ignore_pings') && in_array($comment['comment_type'], array('pingback', 'trackback'))) {
if (@empty($_SERVER['REMOTE_ADDR']) || @empty($comment['comment_author_url']) || @empty($comment['comment_content'])) {
return $this->mark_comment_request($comment);
} else {
$parse_url = parse_url($comment['comment_author_url']);
if (gethostbyname($parse_url['host']) != $_SERVER['REMOTE_ADDR']) {
return $this->mark_comment_request($comment);
} 
}
}
return $comment;
}
function mark_comment_request($comment) {
if (!get_option('antispam_bee_flag_spam')) {
die('Spam deleted.');
}
$ignore_type = get_option('antispam_bee_ignore_type');
$is_ping = in_array($comment['comment_type'], array('pingback', 'trackback'));
if (get_option('antispam_bee_ignore_filter') && (($ignore_type == 1 && $is_ping) || ($ignore_type == 2 && !$is_ping))) {
die('Spam deleted.'); 
}
add_filter(
'pre_comment_approved',
create_function(
'',
'return "spam";'
)
);
if (!get_option('antispam_bee_no_notice')) {
$comment['comment_content'] = "[MARKED AS SPAM BY ANTISPAM BEE]\n" .$comment['comment_content'];
}
return $comment;
}
}
new Antispam_Bee();