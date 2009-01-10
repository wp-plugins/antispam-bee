<?php
/*
Plugin Name: Antispam Bee
Plugin URI: http://playground.ebiene.de/1137/antispam-bee-wordpress-plugin/
Description: Antispam Bee - The easy and effective Antispam Plugin for WordPress.
Author: Sergej M&uuml;ller
Version: 0.2
Author URI: http://wordpress-coder.de
*/


class Antispam_Bee {
function Antispam_Bee() {
$this->protect = 'comment-' .substr(md5(get_bloginfo('home')), 0, 5);
if (is_admin()) {
add_action(
'admin_menu',
array(
$this,
'setup'
)
);
add_action(
'activate_' .plugin_basename(__FILE__),
array(
$this,
'activate'
)
);
add_filter(
'plugin_action_links',
array(
$this,
'actions'
),
10,
2
);
} else {
add_action(
'preprocess_comment',
array(
$this,
'mark'
),
1,
1
);
add_action(
'template_redirect',
array(
$this,
'init'
)
);
add_action(
'init',
array(
$this,
'check'
)
);
}
}
function actions($links, $file) {
$plugin = plugin_basename(__FILE__);
if ($file == $plugin) {
return array_merge(
array(
sprintf(
'<a href="options-general.php?page=%s">%s</a>',
$plugin,
__('Settings')
)
),
$links
);
}
return $links;
}
function activate() {
add_option('antispam_bee_flag_spam');
}
function setup() {
add_options_page(
'Antispam Bee',
(version_compare($GLOBALS['wp_version'], '2.6.999', '>') ? '<img src="' .@plugins_url('antispam-bee/img/icon.png'). '" width="11" height="9" alt="Antispam Bee Icon" />' : ''). 'Antispam Bee',
9,
__FILE__,
array(
$this,
'settings'
)
);
}
function settings() {
if (isset($_POST) && !empty($_POST)) {
if (function_exists('current_user_can') === true && (current_user_can('manage_options') === false || current_user_can('edit_plugins') === false)) {
wp_die("You don't have permission to access!");
}
check_admin_referer('antispam_bee');
update_option(
'antispam_bee_flag_spam',
(@$_POST['antispam_bee_flag_spam'] ? intval($_POST['antispam_bee_flag_spam']) : '')
); ?>
<div id="message" class="updated fade">
<p>
<strong>
<?php _e('Settings saved.') ?>
</strong>
</p>
</div>
<?php } ?>
<div class="wrap">
<?php if (version_compare($GLOBALS['wp_version'], '2.6.999', '>')) { ?>
<div class="icon32" style="background: url(<?php echo @plugins_url('antispam-bee/img/icon32.png') ?>) no-repeat"><br /></div>
<?php } ?>
<h2>
Antispam Bee
</h2>
<form method="post" action="">
<?php wp_nonce_field('antispam_bee') ?>
<div id="poststuff" class="ui-sortable">
<div id="wp_seo_about_wpseo" class="postbox">
<h3>
<?php _e('Settings') ?>
</h3>
<div class="inside">
<table class="form-table">
<tr>
<td>
<label for="antispam_bee_flag_spam">
<input type="checkbox" name="antispam_bee_flag_spam" id="antispam_bee_flag_spam" value="1" <?php echo (get_option('antispam_bee_flag_spam')) ? 'checked="checked"' : '' ?> />
<?php echo (get_locale() == 'de_DE' ? 'Spam markieren, nicht löschen' : 'Mark as Spam, do not delete') ?>
</label>
</td>
</tr>
</table>
<p>
<input type="submit" name="antispam_bee_submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>
</div>
</div>
</div>
</form>
</div>
<?php }
function init() {
if (is_singular()) {
ob_start(
create_function(
'$input',
'return preg_replace("#textarea(.*?)name=([\"\'])comment([\"\'])(.+)</textarea>#", "textarea$1name=$2' .$this->protect. '$3$4</textarea><textarea name=\"comment\" rows=\"1\" cols=\"1\" style=\"display:none\"></textarea>", $input);'
)
);
}
}
function check() {
if (strpos($_SERVER['REQUEST_URI'], 'wp-comments-post.php') !== false && isset($_POST) && !empty($_POST)) {
if (isset($_POST['comment']) && isset($_POST[$this->protect]) && empty($_POST['comment']) && !empty($_POST[$this->protect])) {
$_POST['comment'] = $_POST[$this->protect];
unset($_POST[$this->protect]);
} else {
if (get_option('antispam_bee_flag_spam')) {
$_POST['is_spam'] = 1;
} else {
unset($_POST['comment']);
}
}
}
}
function mark($comment) {
if (strpos($_SERVER['REQUEST_URI'], 'wp-comments-post.php') !== false && isset($_POST) && !empty($_POST)) {
if (isset($_POST['comment']) && isset($_POST[$this->protect]) && isset($_POST['is_spam']) && !empty($_POST['is_spam'])) {
add_filter(
'pre_comment_approved',
create_function(
'$a',
'return "spam";'
)
);
$comment['comment_content'] = "[MARKED FOR SPAM BY ANTISPAM BEE]\n" .$comment['comment_content'];
unset($_POST['is_spam']);
}
}
return $comment;
}
}
if (class_exists('Antispam_Bee') && function_exists('is_admin')) {
$GLOBALS['Antispam_Bee'] = new Antispam_Bee();
}