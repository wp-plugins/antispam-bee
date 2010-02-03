<?php
if (defined('WP_UNINSTALL_PLUGIN')) {
	/* Cronjob löschen */
	if (wp_next_scheduled('antispam_bee_daily_cronjob')) {
		wp_clear_scheduled_hook('antispam_bee_daily_cronjob');
	}
	
	/* Option löschen */
	delete_option('antispam_bee');
	
	/* DB reinigen */
	$GLOBALS['wpdb']->query("OPTIMIZE TABLE `" .$GLOBALS['wpdb']->options. "`");
}
?>