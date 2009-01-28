<?php
if (defined('WP_UNINSTALL_PLUGIN')) {
	delete_option('antispam_bee_flag_spam');
	delete_option('antispam_bee_ignore_pings');
}
?>