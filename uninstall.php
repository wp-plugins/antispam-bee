<?php
if (defined('WP_UNINSTALL_PLUGIN')) {
	/* Cronjob löschen */
	if (wp_next_scheduled('antispam_bee_daily_cronjob')) {
		wp_clear_scheduled_hook('antispam_bee_daily_cronjob');
	}
	
	/* DB bereinigen */
	$GLOBALS['wpdb']->query("DELETE FROM `" .$GLOBALS['wpdb']->options. "` WHERE option_name LIKE 'antispam_bee_%'");
	$GLOBALS['wpdb']->query("OPTIMIZE TABLE `" .$GLOBALS['wpdb']->options. "`");
}
?>