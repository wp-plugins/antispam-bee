<?php
if (defined('WP_UNINSTALL_PLUGIN')) {
	/* Cronjob löschen */
	if (wp_next_scheduled('antispam_bee_daily_cronjob')) {
		wp_clear_scheduled_hook('antispam_bee_daily_cronjob');
	}
 	
 	/* Optionen löschen */
	delete_option('antispam_bee_flag_spam');
	delete_option('antispam_bee_ignore_pings');
	delete_option('antispam_bee_no_notice');
	delete_option('antispam_bee_cronjob_enable');
	delete_option('antispam_bee_cronjob_interval');
	delete_option('antispam_bee_cronjob_timestamp');
}
?>