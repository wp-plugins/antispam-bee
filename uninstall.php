<?php
if (defined('WP_UNINSTALL_PLUGIN')) {
	/* Remove settings */
	delete_option('antispam_bee');
	
	/* Clean DB */
	$GLOBALS['wpdb']->query("OPTIMIZE TABLE `" .$GLOBALS['wpdb']->options. "`");
}
?>