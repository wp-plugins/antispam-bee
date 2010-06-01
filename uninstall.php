<?php
/* Remove settings */
delete_option('antispam_bee');

/* Clean DB */
$GLOBALS['wpdb']->query("OPTIMIZE TABLE `" .$GLOBALS['wpdb']->options. "`");