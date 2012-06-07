<?php
/*
Plugin Name: Antispam Bee
Text Domain: antispam_bee
Domain Path: /lang
Description: Easy and extremely productive spam-fighting plugin with many sophisticated solutions. Includes protection again trackback spam.
Author: Sergej M&uuml;ller
Author URI: http://www.wpSEO.org
Plugin URI: http://antispambee.com
Version: 2.2
*/


/* Sicherheitsabfrage */
if ( !class_exists('WP') ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}


/**
* Antispam_Bee
*
* @since 0.1
*/

class Antispam_Bee {


	/* Save me! */
	var $md5_sign;
	var $base_name;
	var $spam_reason;


	/**
	* Konstruktor der Klasse
	*
	* @since 	0.1
	* @change	1.9
	*/

	function Antispam_Bee() {
		/* AJAX & Co. */
		if ( (defined('DOING_AJAX') && DOING_AJAX) or (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ) {
			return;
		}

		/* Initialisierung */
		$this->base_name = plugin_basename(__FILE__);
		$this->md5_sign = 'comment-' .substr(md5(get_bloginfo('url')), 0, 8);

		/* Cronjob */
		if ( defined('DOING_CRON') ) {
			add_action(
				'antispam_bee_daily_cronjob',
				array(
					$this,
					'exe_daily_cronjob'
				)
			);

		/* Admin */
		} elseif ( is_admin() ) {
			/* Menü */
			add_action(
				'admin_menu',
				array(
					$this,
					'init_admin_menu'
				)
			);

			/* GUI */
			if ( $this->is_current_page('home') ) {
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

			/* Dashboard */
			} else if ( $this->is_current_page('index') ) {
				add_action(
					'init',
					array(
						$this,
						'load_plugin_lang'
					)
				);

				if ( $this->get_option('dashboard_count') ) {
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

				if ( $this->get_option('dashboard_chart') ) {
					add_action(
						'wp_dashboard_setup',
						array(
							$this,
							'init_dashboard_chart'
						)
					);
				}

			/* Plugins */
			} else if ( $this->is_current_page('plugins') ) {
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


				add_filter(
					'plugin_row_meta',
					array(
						$this,
						'init_row_meta'
					),
					10,
					2
				);
			}

		/* Frontend */
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


	/**
	* Einbindung der Plugin-Sprache
	*
	* @since  0.1
	* @change 0.1
	*/

	function load_plugin_lang() {
		load_plugin_textdomain(
			'antispam_bee',
			false,
			'antispam-bee/lang'
		);
	}


	/**
	* Links in der Plugins-Verwaltung
	*
	* @since  0.1
	* @change 2.1
	*
	* @param	array   $links  Array mit Links
	* @param	string  $file   Name des Plugins
	* @return	array   $links  Array mit erweitertem Link
	*/

	function init_row_meta($links, $file) {
		if ( $this->base_name == $file ) {
			return array_merge(
				$links,
				array(
					sprintf(
						'<a href="https://flattr.com/thing/54115/Antispam-Bee-Das-WordPress-Plugin-fur-den-Schutz-gegen-Spam" target="_blank">%s</a>',
						esc_html__('Flattr')
					),
					sprintf(
						'<a href="options-general.php?page=%s">%s</a>',
						$this->base_name,
						esc_html__('Settings')
					)
				)
			);
		}

		return $links;
	}


	/**
	* Aktion bei Aktivierung des Plugins
	*
	* @since  0.1
	* @change 2.0
	*/

	function init_plugin_options() {
		/* Option anlegen */
		add_option(
			'antispam_bee',
			array(),
			'',
			'no'
		);

		/* Cron aktivieren */
		if ($this->get_option('cronjob_enable')) {
			$this->init_scheduled_hook();
		}
	}


	/**
	* Rückgabe eines Optionsfeldes
	*
	* @since  0.1
	* @change 0.1
	*
	* @param	string	$field  Name des Feldes
	* @return	mixed						Wert des Feldes
	*/

	function get_option($field) {
		if ( !$options = wp_cache_get('antispam_bee') ) {
			$options = get_option('antispam_bee');

			wp_cache_set(
				'antispam_bee',
				$options
			);
		}

		return @$options[$field];
	}


	/**
	* Aktualisiert ein Optionsfeld
	*
	* @since  0.1
	* @change 1.6
	*
	* @param	string	$field  Name des Feldes
	* @param	mixed						Wert des Feldes
	*/

	function update_option($field, $value) {
		$this->update_options(
			array(
				$field => $value
			)
		);
	}


	/**
	* Aktualisiert mehrere Optionsfelder
	*
	* @since  0.1
	* @change 1.6
	*
	* @param	array	 $data  Array mit Feldern
	*/

	function update_options($data) {
		/* Option zuweisen */
		$options = array_merge(
			(array)get_option('antispam_bee'),
			$data
		);

		/* DB updaten */
		update_option(
			'antispam_bee',
			$options
		);

		/* Cache updaten */
		wp_cache_set(
			'antispam_bee',
			$options
		);
	}


	/**
	* Initialisierung des Cronjobs
	*
	* @since  0.1
	* @change 1.6
	*/

	function init_scheduled_hook() {
		if ( !wp_next_scheduled('antispam_bee_daily_cronjob') ) {
			wp_schedule_event(
				time(),
				'daily',
				'antispam_bee_daily_cronjob'
			);
		}
	}


	/**
	* Beendigung des Cronjobs
	*
	* @since  0.1
	* @change 1.6
	*/

	function clear_scheduled_hook() {
		if ( wp_next_scheduled('antispam_bee_daily_cronjob') ) {
			wp_clear_scheduled_hook('antispam_bee_daily_cronjob');
		}
	}


	/**
	* Ausführung des Cronjobs
	*
	* @since  0.1
	* @change 1.6
	*/

	function exe_daily_cronjob() {
		/* Kein Cronjob? */
		if ( !$this->get_option('cronjob_enable') ) {
			return;
		}

		/* Timestamp updaten */
		$this->update_option(
			'cronjob_timestamp',
			time()
		);

		/* Spam löschen */
		$this->delete_spam_comments();
	}


	/**
	* Löschung alter Spamkommentare
	*
	* @since  0.1
	* @change 2.0
	*/

	function delete_spam_comments() {
		/* Anzahl der Tage */
		$days = (int)$this->get_option('cronjob_interval');

		/* Kein Wert? */
		if ( empty($days) ) {
			return false;
		}

		/* Global */
		global $wpdb;

		/* Kommentare löschen */
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `$wpdb->comments` WHERE `comment_approved` = 'spam' AND SUBDATE(NOW(), %d) > comment_date_gmt",
				$days
			)
		);

		/* DB optimieren */
		$wpdb->query("OPTIMIZE TABLE `$wpdb->comments`");
	}


	/**
	* Initialisierung der GUI
	*
	* @since  0.1
	* @change 0.1
	*/

	function init_admin_menu() {
		/* Menü anlegen */
		$page = add_options_page(
			'Antispam Bee',
			'<img src="' .plugins_url('antispam-bee/img/icon.png'). '" id="ab_icon" alt="Antispam Bee" />Antispam Bee',
			'manage_options',
			__FILE__,
			array(
				$this,
				'show_admin_menu'
			)
		);

		/* JS einbinden */
		add_action(
			'admin_print_scripts-' . $page,
			array(
				$this,
				'add_enqueue_script'
			)
		);

		/* CSS einbinden */
		add_action(
			'admin_print_styles-' . $page,
			array(
				$this,
				'add_enqueue_style'
			)
		);
	}


	/**
	* Registrierung von Plugin-spezifische Dateien
	*
	* @since  1.6
	* @change 1.6
	*/

	function add_plugin_sources() {
		/* Infos auslesen */
		$data = get_plugin_data(__FILE__);

		/* JS einbinden */
		wp_register_script(
			'ab_script',
			plugins_url('antispam-bee/js/script.js'),
			array('jquery'),
			$data['Version']
		);

		/* CSS einbinden */
		wp_register_style(
			'ab_style',
			plugins_url('antispam-bee/css/style.css'),
			array(),
			$data['Version']
		);
	}


	/**
	* Initialisierung von JavaScript
	*
	* @since  1.6
	* @change 1.6
	*/

	function add_enqueue_script() {
		wp_enqueue_script('ab_script');
	}


	/**
	* Initialisierung von Stylesheets
	*
	* @since  1.6
	* @change 1.6
	*/

	function add_enqueue_style() {
		wp_enqueue_style('ab_style');
	}


	/**
	* Prüfung der WordPress-Version
	*
	* @since  0.1
	* @change 0.1
	*
	* @param	integer	 $version  Gesuchte WP-Version
	* @return	boolean						 TRUE, wenn mindestens gesuchte
	*/

	function is_min_wp($version) {
		return version_compare(
			$GLOBALS['wp_version'],
			$version. 'alpha',
			'>='
		);
	}


	/**
	* Prüfung der PHP-Version
	*
	* @since  1.9
	* @change 1.9
	*
	* @param	integer	 $version  Gesuchte PHP-Version
	* @return	boolean						 TRUE, wenn mindestens gesuchte
	*/

  function is_min_php($version) {
  	return version_compare(
  		phpversion(),
  		$version,
  		'>='
  	);
  }


	/**
	* Prüfung auf das wpTouch Plugin
	*
	* @since  0.1
	* @change 2.0
	*
	* @return	boolean		TRUE, wenn Plugin aktiv und aufgerufen
	*/

	function is_mobile() {
		return strpos(TEMPLATEPATH, 'wptouch');
	}


	/**
	* Prüfung der Admin-Seite
	*
	* @since  0.1
	* @change 2.0
	*
	* @param	integer	 $page  Gesuchte Seite
	* @return	boolean					TRUE, wenn die aktuelle auch die gesuchte Seite ist
	*/

	function is_current_page($page) {
		switch($page) {
			case 'index':
				return ( empty($GLOBALS['pagenow']) or ( !empty($GLOBALS['pagenow']) && $GLOBALS['pagenow'] == 'index.php' ) );

			case 'home':
				return ( !empty($_REQUEST['page']) && $_REQUEST['page'] == $this->base_name );

			case 'plugins':
				return ( !empty($GLOBALS['pagenow']) && $GLOBALS['pagenow'] == 'plugins.php' );

			default:
				return false;
		}
	}


	/**
	* Anzeige des Spam-Counters auf dem Dashboard (bis WP 3.0)
	*
	* @since  0.1
	* @change 1.6
	*/

	function add_table_end() {
		echo sprintf(
			'<tr>
				<td class="first b b-tags"></td>
				<td class="t tags"></td>
				<td class="b b-spam" style="font-size:18px">%s</td>
				<td class="last t">%s</td>
			</tr>',
			esc_html($this->get_spam_count()),
			esc_html__('Blocked', 'antispam_bee')
		);
	}


	/**
	* Anzeige des Spam-Counters auf dem Dashboard (ab WP 3.0)
	*
	* @since  0.1
	* @change 1.6
	*/

	function add_discussion_table_end() {
		echo sprintf(
			'<tr>
				<td class="b b-spam" style="font-size:18px">%s</td>
				<td class="last t">%s</td>
			</tr>',
			esc_html($this->get_spam_count()),
			esc_html__('Blocked', 'antispam_bee')
		);
	}


	/**
	* Initialisierung des Dashboard-Chart
	*
	* @since  1.9
	* @change 2.0
	*/

	function init_dashboard_chart() {
		/* Keine Rechte? */
		if ( !current_user_can('administrator') or !$this->is_min_php('5.0.2') ) {
			return false;
		}

		/* Widget hinzufügen */
		wp_add_dashboard_widget(
			'ab_spam_chart',
			'Antispam Bee',
			array(
				$this,
				'show_spam_chart'
			)
		);

		/* JS laden */
		add_action(
			'wp_print_scripts',
			array(
				$this,
				'add_dashboard_js'
			)
		);

		/* CSS laden */
		add_action(
			'admin_head',
			array(
				$this,
				'add_dashboard_css'
			)
		);
	}


	/**
	* Ausgabe von Dashboard-CSS
	*
	* @since  1.9
	* @change 2.2
	*/

	function add_dashboard_css() {
		/* Infos auslesen */
		$data = get_plugin_data(__FILE__);
		
		/* CSS registrieren */
		wp_register_style(
			'antispambee',
			plugins_url('antispam-bee/css/dashboard.css'),
			array(),
			$data['Version']
		);

		/* CSS ausgeben */
  	wp_print_styles('antispambee');
	}


	/**
	* Ausgabe von Dashboard-JS
	*
	* @since  1.9
	* @change 2.2
	*/

	function add_dashboard_js() {
		/* Init */
		$items = (array)$this->get_option('daily_stats');

		/* Leer? */
		if ( empty($items) or count($items) == 1 ) {
			return;
		}

		/* Sortieren */
		krsort($items, SORT_NUMERIC);
		
		/* Init */
		$output = array(
			'created' => array(),
			'count' => array()
		);
		
		/* Init */
		$i = 0;

		/* Zeilen loopen */
		foreach($items as $timestamp => $count) {
			array_push(
				$output['created'],
				( $timestamp == strtotime('today', current_time('timestamp')) ? __('Today', 'antispam_bee') : date('d.m', $timestamp) )
			);
			array_push(
				$output['count'],
				(int)$count
			);
		}

		/* Zusammenfassen */
		$stats = array(
			'created' => implode(',', $output['created']),
			'count' => implode(',', $output['count'])
		);

		/* Infos auslesen */
		$data = get_plugin_data(__FILE__);

		/* JS einbinden */
		wp_register_script(
			'ab_chart',
			plugins_url('antispam-bee/js/dashboard.js'),
			array('jquery'),
			$data['Version']
		);
		wp_register_script(
			'google_jsapi',
			'http://www.google.com/jsapi',
			false
		);

		/* Einbinden */
		wp_enqueue_script('google_jsapi');
		wp_enqueue_script('ab_chart');

		/* Übergeben */
		wp_localize_script(
			'ab_chart',
			'antispambee',
			$stats
		);
	}


	/**
	* Ausgabe des Dashboard-Chart
	*
	* @since  1.9
	* @change 2.0
	*/

	function show_spam_chart() {
		echo '<div id="ab_chart"></div>';
	}


	/**
	* Anzeige des Version-Hinweises
	*
	* @since  0.1
	* @change 2.0
	*/

	function show_version_notice() {
		/* Keine Ausgabe? */
		if ( $this->is_min_wp('2.8') ) {
			return;
		}

		/* Warnung */
		echo sprintf(
			'<div class="error"><p><strong>Antispam Bee</strong> %s</p></div>',
			esc_html__('requires at least WordPress 2.8', 'antispam_bee')
		);
	}


	/**
	* Kürzung der IP-Adressen
	*
	* @since  0.1
	* @change 0.1
	*
	* @param	string	$ip  Ungekürzte IP
	* @return	string	$ip  Gekürzte IP
	*/

	function cut_ip_addr($ip) {
		if ( !empty($ip) ) {
			return str_replace(
				strrchr($ip, '.'),
				'',
				$ip
			);
		}
	}


	/**
	* Ersetzung der Kommentar-Textarea
	*
	* @since  0.1
	* @change 2.0
	*/

	function replace_comment_field() {
		/* Nur Frontend */
		if ( is_feed() or is_trackback() or is_robots() or $this->is_mobile() ) {
			return;
		}

		/* Nur Beiträge */
		if ( !is_singular() && !$this->get_option('always_allowed') ) {
			return;
		}

		/* Replace! */
		ob_start(
			create_function(
				'$input',
				'return preg_replace("#<textarea(.*?)name=([\"\'])comment([\"\'])(.+?)</textarea>#s", "<textarea$1name=$2' .$this->md5_sign. '$3$4</textarea><textarea name=\"comment\" style=\"display:none\" rows=\"1\" cols=\"1\"></textarea>", $input, 1);'
			)
		);
	}


	/**
	* Prüfung bestehenden Spam
	*
	* @since  2.0
	* @change 2.0
	*
	* @param	string	 $ip  IP-Adresse
	* @return	boolean       TRUE bei vorhandenem Spam
	*/

	function is_ip_spam($ip) {
		/* Leer? */
		if ( empty($ip) ) {
			return true;
		}

		/* Global */
		global $wpdb;

		/* Suchen */
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `comment_ID` FROM `$wpdb->comments` WHERE `comment_approved` = 'spam' AND `comment_author_IP` = %s LIMIT 1",
				(string)$ip
			)
		);

		/* Gefunden? */
		if ( $found ) {
			return true;
		}

		return false;
	}


	/**
	* Prüfung auf bereits kommentierte E-Mail-Adresse
	*
	* @since  2.0
	* @change 2.0
	*
	* @param	string	 $email  E-Mail-Adresse
	* @return	boolean       	 TRUE bei gefundenem Eintrag
	*/

	function is_already_commented($email) {
		/* Leer? */
		if ( empty($email) ) {
			return false;
		}

		/* Global */
		global $wpdb;

		/* Suchen */
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `comment_ID` FROM `$wpdb->comments` WHERE `comment_approved` = '1' AND `comment_author_email` = %s LIMIT 1",
				(string)$email
			)
		);

		/* Gefunden? */
		if ( $found ) {
			return true;
		}

		return false;
	}


	/**
	* Prüfung auf erlaubten Ländercode
	*
	* @since  0.1
	* @change 2.0
	*
	* @param	string	 $ip  IP-Adresse
	* @return	boolean       TRUE bei gesperrtem Land
	*/

	function is_blacklist_country($ip) {
		/* API Key */
		$key = $this->get_option('ipinfodb_key');

		/* Keine Daten? */
		if ( empty($ip) or empty($key) ) {
			return false;
		}

		/* White & Black */
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

		/* Keine Listen? */
		if ( empty($white) && empty($black) ) {
			return false;
		}

		/* IP abfragen */
		$response = wp_remote_get(
			sprintf(
				'http://api.ipinfodb.com/v2/ip_query_country.php?key=%s&ip=%s',
				$key,
				$ip
			)
		);

		/* Fehler? */
		if ( is_wp_error($response) ) {
			return false;
		}

		/* XML lesen */
		preg_match(
			'#Code>([A-Z]{2})</Country#i',
			wp_remote_retrieve_body($response),
			$matches
		);

		/* Kein Code? */
		if ( empty($matches[1]) ) {
			return false;
		}

		/* Blacklist */
		if ( !empty($black) ) {
			return ( in_array($matches[1], $black) ? true : false );
		}

		/* Whitelist */
		return ( in_array($matches[1], $white) ? false : true );
	}


	/**
	* Prüfung auf Honey Pot Spam
	*
	* @since  1.7
	* @change 2.0
	*
	* @param	string	 $ip  IP-Adresse
	* @return	boolean       TRUE bei gemeldeter IP
	*/

	function is_honey_spam($ip) {
		/* API Key */
		$key = $this->get_option('honey_key');

		/* Keine Daten? */
		if ( empty($ip) or empty($key) ) {
			return false;
		}

		/* Host */
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

		/* Response */
		$bits = explode(
			'.',
			gethostbyname($host)
		);

		return ( $bits[0] == 127 && $bits[3] & 4 );
	}


	/**
	* Prüfung auf Blacklisted-Sprache
	*
	* @since  2.0
	* @change 2.1
	*
	* @param	str  $content  Content
	* @return	bool 					 TRUE bei Spam
	*/

	function is_lang_spam($content)
	{
		/* Init */
		$lang = $this->get_option('translate_lang');

		/* Formatieren */
		$content = rawurlencode(
			mb_substr(
				strip_tags(stripslashes($content)),
				0,
				200
			)
		);

		/* Keine Daten? */
		if ( empty($lang) or empty($content) ) {
			return false;
		}

		/* IP abfragen */
		$response = wp_remote_get(
			sprintf(
				'http://translate.google.de/translate_a/t?client=x&text=%s',
				$content
			)
		);

		/* Fehler? */
		if ( is_wp_error($response) ) {
			return false;
		}

		/* Parsen */
		preg_match(
			'/"src":"(\\D{2})"/',
			wp_remote_retrieve_body($response),
			$matches
		);

		/* Fehler? */
		if ( empty($matches[1]) ) {
			return false;
		}

		return ( $matches[1] != $lang );
	}


	/**
	* Prüfung auf gefälschte IP
	*
	* @since  2.0
	* @change 2.0
	*
	* @param	string	 $ip  IP-Adresse
	* @return	boolean       TRUE bei gefälschter IP
	*/

	function is_fake_ip($ip)
	{
		/* Leer? */
		if ( empty($ip) ) {
			return true;
		}

		/* Suchen */
		$found = strpos(
			$ip,
			$this->cut_ip_addr(
				gethostbyname(
					gethostbyaddr($ip)
				)
			)
		);

		return $found === false;
	}


	/**
	* Kontrolle der POST-Werte
	*
	* @since  0.1
	* @change 0.1
	*/

	function precheck_comment_request() {
		/* Nur Frontend */
		if ( is_feed() or is_trackback() or $this->is_mobile() ) {
			return;
		}

		/* Allgemeine Werte */
		$request_url = @$_SERVER['REQUEST_URI'];
		$hidden_field = @$_POST['comment'];
		$plugin_field = @$_POST[$this->md5_sign];

		/* Falsch verbunden */
		if ( empty($_POST) or empty($request_url) or strpos($request_url, 'wp-comments-post.php') === false ) {
			return;
		}

		/* Felder prüfen */
		if (empty($hidden_field) && !empty($plugin_field)) {
			$_POST['comment'] = $plugin_field;
			unset($_POST[$this->md5_sign]);
		} else {
			$_POST['bee_spam'] = 1;
		}
	}


	/**
	* Prüfung und Markierung des Kommentars als Spam
	*
	* @since  0.1
	* @change 2.1
	*
	* @param	array		$comment	Unbehandelter Kommentar
	* @return	array		$comment	Behandelter Kommentar
	*/

	function verify_comment_request($comment) {
		/* Server-Werte */
		$request_url = @$_SERVER['REQUEST_URI'];
		$request_ip = @$_SERVER['REMOTE_ADDR'];

		/* Werte leer? */
		if ( empty($request_url) or empty($request_ip) ) {
			return $this->flag_comment_request(
				$comment,
				'Empty Data'
			);
		}

		/* Kommentar-Werte */
		$comment_type = @$comment['comment_type'];
		$comment_url = @$comment['comment_author_url'];
		$comment_body = @$comment['comment_content'];
		$comment_email = @$comment['comment_author_email'];

		/* Ping-Werte */
		$ping_types = array('pingback', 'trackback', 'pings');
		$ping_allowed = !$this->get_option('ignore_pings');

		/* Pingback Host */
		if ( !empty($comment_url) ) {
			$comment_parse = @parse_url($comment_url);
			$comment_host = @$comment_parse['host'];
		}

		/* Kommentar */
		if ( strpos($request_url, 'wp-comments-post.php') !== false && !empty($_POST) ) {

			/* Bereits kommentiert? */
			if ( $this->get_option('already_commented') && $this->is_already_commented($comment_email) ) {
				return $comment;
			}

			/* Bot erkannt */
			if ( !empty($_POST['bee_spam']) ) {
				return $this->flag_comment_request(
					$comment,
					'CSS Hack'
				);
			}

			/* Erweiterter Schutz */
			if ( $this->get_option('advanced_check') && $this->is_fake_ip($request_ip) ) {
				return $this->flag_comment_request(
					$comment,
					'Server IP'
				);
			}

			/* IPs im Spam */
			if ( $this->get_option('spam_ip') && $this->is_ip_spam($request_ip) ) {
				return $this->flag_comment_request(
					$comment,
					'Spam IP'
				);
			}

			/* Translate API */
			if ( $this->get_option('translate_api') && $this->is_lang_spam($comment_body) ) {
				return $this->flag_comment_request(
					$comment,
					'Comment Language'
				);
			}

			/* Country Code */
			if ( $this->get_option('country_code') && $this->is_blacklist_country($request_ip) ) {
				return $this->flag_comment_request(
					$comment,
					'Country Check'
				);
			}

			/* Honey Pot */
			if ( $this->get_option('honey_pot') && $this->is_honey_spam($request_ip) ) {
				return $this->flag_comment_request(
					$comment,
					'Honey Pot'
				);
			}

		/* Trackback */
		} else if ( !empty($comment_type) && in_array($comment_type, $ping_types) && $ping_allowed ) {
			/* Leere Werte ? */
			if ( empty($comment_url) or empty($comment_body) ) {
				return $this->flag_comment_request(
					$comment,
					'Empty Data',
					true
				);
			}

			/* IP != Server */
			if ( !empty($comment_host) && gethostbyname($comment_host) != $request_ip ) {
				return $this->flag_comment_request(
					$comment,
					'Server IP',
					true
				);
			}

			/* IPs im Spam */
			if ( $this->get_option('spam_ip') && $this->is_ip_spam($request_ip) === true ) {
				return $this->flag_comment_request(
					$comment,
					'Spam IP',
					true
				);
			}

			/* Country Code prüfen */
			if ( $this->get_option('country_code') && $this->is_blacklist_country($request_ip) ) {
				return $this->flag_comment_request(
					$comment,
					'Country Check',
					true
				);
			}

			/* Honey Pot */
			if ( $this->get_option('honey_pot') && $this->is_honey_spam($request_ip) ) {
				return $this->flag_comment_request(
					$comment,
					'Honey Pot',
					true
				);
			}
		}

		return $comment;
	}


	/**
	* Ausführung des Markier-Vorgangs
	*
	* @since  0.1
	* @change 1.7
	*
	* @param	array    $comment	 Unbehandelter Kommentar
	* @param	string   $reason	 Verdachtsgrund
	* @param	boolean  $is_ping	 Ping (ja oder nein) [optional]
	* @return	array		 $comment	 Behandelter Kommentar
	*/

	function flag_comment_request($comment, $reason, $is_ping = false) {
		/* Spam-Einstellungen */
		$spam_remove = !$this->get_option('flag_spam');
		$spam_notice = !$this->get_option('no_notice');

		/* Filter-Einstellungen */
		$ignore_filter = $this->get_option('ignore_filter');
		$ignore_type = $this->get_option('ignore_type');

		/* Spam hochzählen */
		$this->update_spam_count();
		$this->update_daily_stats();

		/* Spam löschen */
		if ( $spam_remove ) {
			die('Spam deleted.');
		}

		/* Typen behandeln */
		if ( $ignore_filter && (($ignore_type == 1 && $is_ping) or ($ignore_type == 2 && !$is_ping)) ) {
			die('Spam deleted.');
		}

		/* Spam-Grund */
		$this->spam_reason = $reason;

		/* Spam markieren */
		add_filter(
			'pre_comment_approved',
			create_function(
				'',
				'return "spam";'
			)
		);

		/* E-Mail senden */
		add_filter(
			'comment_post',
			array(
				$this,
				'send_email_notify'
			)
		);

		/* Notiz setzen */
		if ( $spam_notice ) {
			$comment['comment_content'] = sprintf(
				'[MARKED AS SPAM BY ANTISPAM BEE | %s]%s%s',
				$reason,
				"\n",
				$comment['comment_content']
			);
		}

		return $comment;
	}


	/**
	* Ersetzung des Whois Links
	*
	* @since  1.7
	* @change 1.7
	*
	* @param	string  $body  Body mit Whois
	* @return	string	$body  Body mit IPinfoDB
	*/

	function replace_whois_link($body) {
		if ( $this->get_option('country_code') ) {
			return preg_replace(
				'/^Whois .+?=(.+?)/m',
				'IP Locator: http://ipinfodb.com/ip_locator.php?ip=$1',
				$body
			);
		}

		return $body;
	}


	/**
	* Versand einer Benachrichtigung via E-Mail
	*
	* @since  0.1
	* @change 2.0
	*/

	function send_email_notify($id) {
		/* Keine Benachrichtigung? */
		if ( !$this->get_option('email_notify') ) {
			return $id;
		}

		/* Werte initialisieren */
		$comment = @$GLOBALS['commentdata'];
		$ip = @$_SERVER['REMOTE_ADDR'];

		/* Keine Werte? */
		if ( empty($comment) or empty($ip) ) {
			return $id;
		}

		/* Beitrag ermitteln */
		if ( !$post = get_post($comment['comment_post_ID']) ) {
			return $id;
		}

		/* Sprache laden */
		$this->load_plugin_lang();

		/* Betreff */
		$subject = sprintf(
			'[%s] %s',
			get_bloginfo('name'),
			__('Comment marked as spam', 'antispam_bee')
		);

		/* Content */
		if ( !$content = strip_tags(stripslashes($comment['comment_content'])) ) {
			$content = sprintf(
				'-- %s --',
				__('Content removed by Antispam Bee', 'antispam_bee')
			);
		}

		/* Body */
		$body = sprintf(
			"%s \"%s\"\r\n\r\n",
			__('New spam comment on your post', 'antispam_bee'),
			strip_tags($post->post_title)
		).sprintf(
			"%s: %s\r\n",
			__('Author'),
			$comment['comment_author'],
			$ip
		).sprintf(
			"URL: %s\r\n",
			esc_url($comment['comment_author_url'])
		).sprintf(
			"IP Locator: http://ipinfodb.com/ip_locator.php?ip=%s\r\n",
			$ip
		).sprintf(
			"%s: %s\r\n\r\n",
			__('Spam Reason', 'antispam_bee'),
			__($this->spam_reason, 'antispam_bee')
		).sprintf(
			"%s\r\n\r\n\r\n",
			$content
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

		/* Send */
		wp_mail(
			get_bloginfo('admin_email'),
			$subject,
			$body
		);

		return $id;
	}


	/**
	* Rückgabe der Anzahl von Spam-Kommentaren
	*
	* @since  0.1
	* @change 1.7
	*
	* @param	intval		Anzahl der Spam-Kommentare
	*/

	function get_spam_count() {
		/* Init */
		$count = $this->get_option('spam_count');

		/* Fire */
		return ( get_locale() == 'de_DE' ? number_format($count, 0, '', '.') : number_format_i18n($count) );
	}


	/**
	* Ausgabe der Anzahl von Spam-Kommentaren
	*
	* @since  0.1
	* @change 0.1
	*/

	function the_spam_count() {
		echo esc_html($this->get_spam_count());
	}


	/**
	* Aktualisierung der Anzahl von Spam-Kommentaren
	*
	* @since  0.1
	* @change 0.1
	*/

	function update_spam_count() {
		$this->update_option(
			'spam_count',
			intval($this->get_option('spam_count') + 1)
		);
	}


	/**
	* Aktualisierung der Statistik
	*
	* @since  1.9
	* @change 1.9
	*/

	function update_daily_stats() {
		/* Init */
		$stats = (array)$this->get_option('daily_stats');
		$today = (int)strtotime('today');

		/* Hochzählen */
		if ( array_key_exists($today, $stats) ) {
			$stats[$today] ++;
		} else {
			$stats[$today] = 1;
		}

		/* Sortieren */
		krsort($stats, SORT_NUMERIC);

		/* Speichern */
		$this->update_option(
			'daily_stats',
			array_slice($stats, 0, 31, true)
		);
	}


  /**
	* Anzeige des Hilfe-Links
	*
	* @since  0.1
	* @change 1.6
	*
	* @param	string  $anchor  Anker in der Hilfe
	*/

  function show_help_link($anchor) {
  	/* Nicht Deutsch? */
  	if ( get_locale() != 'de_DE' ) {
  		return '';
  	}

  	/* Ausgeben */
  	echo sprintf(
			'[<a href="http://playground.ebiene.de/1137/antispam-bee-wordpress-plugin/#%s" target="_blank">?</a>]',
			$anchor
		);
  }


	/**
	* Anzeige der GUI
	*
	* @since  0.1
	* @change 2.1
	*/

	function show_admin_menu() {
		if ( !empty($_POST) ) {
			/* Referer prüfen */
			check_admin_referer('antispam_bee');

			/* Optionen ermitteln */
			$options = array(
				'flag_spam' 				=> (int)(!empty($_POST['antispam_bee_flag_spam'])),
				'ignore_pings' 			=> (int)(!empty($_POST['antispam_bee_ignore_pings'])),
				'ignore_filter' 		=> (int)(!empty($_POST['antispam_bee_ignore_filter'])),
				'ignore_type' 			=> (int)(@$_POST['antispam_bee_ignore_type']),
				'no_notice' 				=> (int)(!empty($_POST['antispam_bee_no_notice'])),
				'email_notify' 			=> (int)(!empty($_POST['antispam_bee_email_notify'])),
				'cronjob_enable' 		=> (int)(!empty($_POST['antispam_bee_cronjob_enable'])),
				'cronjob_interval'	=> (int)(@$_POST['antispam_bee_cronjob_interval']),
				'dashboard_count' 	=> (int)(!empty($_POST['antispam_bee_dashboard_count'])),
				'dashboard_chart' 	=> (int)(!empty($_POST['antispam_bee_dashboard_chart'])),
				'advanced_check' 		=> (int)(!empty($_POST['antispam_bee_advanced_check'])),
				'spam_ip' 					=> (int)(!empty($_POST['antispam_bee_spam_ip'])),
				'already_commented'	=> (int)(!empty($_POST['antispam_bee_already_commented'])),
				'always_allowed' 		=> (int)(!empty($_POST['antispam_bee_always_allowed'])),

				'honey_pot' 				=> (int)(!empty($_POST['antispam_bee_honey_pot'])),
				'honey_key'					=> (string)(@$_POST['antispam_bee_honey_key']),

				'country_code' 			=> (int)(!empty($_POST['antispam_bee_country_code'])),
				'country_black'			=> (string)(@$_POST['antispam_bee_country_black']),
				'country_white'			=> (string)(@$_POST['antispam_bee_country_white']),
				'ipinfodb_key'			=> (string)(@$_POST['antispam_bee_ipinfodb_key']),

				'translate_api' 		=> (int)(!empty($_POST['antispam_bee_translate_api'])),
				'translate_lang'		=> (string)(@$_POST['antispam_bee_translate_lang'])
			);

			/* Kein Tag eingetragen? */
			if ( empty($options['cronjob_interval']) ) {
				$options['cronjob_enable'] = 0;
			}


			/* Honey Key reinigen */
			if ( !empty($options['honey_key']) ) {
				$options['honey_key'] = preg_replace(
					'/[^a-z]/',
					'',
					strtolower(
						strip_tags($options['honey_key'])
					)
				);
			}

			/* Kein Honey Key? */
			if ( empty($options['honey_key']) ) {
				$options['honey_pot'] = 0;
			}


			/* Translate API */
			if ( !empty($options['translate_lang']) ) {
				$options['translate_lang'] = preg_replace(
					'/[^den]/',
					'',
					strip_tags($options['translate_lang'])
				);
			}
			if ( empty($options['translate_lang']) ) {
				$options['translate_api'] = 0;
			}


			/* Blacklist reinigen */
			if ( !empty($options['country_black']) ) {
				$options['country_black'] = preg_replace(
					'/[^A-Z ]/',
					'',
					strtoupper(
						strip_tags($options['country_black'])
					)
				);
			}

			/* Whitelist reinigen */
			if ( !empty($options['country_white']) ) {
				$options['country_white'] = preg_replace(
					'/[^A-Z ]/',
					'',
					strtoupper(
						strip_tags($options['country_white'])
					)
				);
			}

			/* Kein IPInfoDB Key? */
			if ( empty($options['ipinfodb_key']) ) {
				$options['country_code'] = 0;
			}

			/* Leere Listen? */
			if ( empty($options['country_black']) && empty($options['country_white']) ) {
				$options['country_code'] = 0;
			}

			/* Cron stoppen? */
			if ( $options['cronjob_enable'] && !$this->get_option('cronjob_enable') ) {
				$this->init_scheduled_hook();
			} else if ( !$options['cronjob_enable'] && $this->get_option('cronjob_enable') ) {
				$this->clear_scheduled_hook();
			}

			/* Optionen speichern */
			$this->update_options($options); ?>

			<div id="message" class="updated fade">
				<p>
					<strong>
						<?php esc_html_e('Settings saved.') ?>
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
							<?php esc_html_e('Settings') ?>
						</h3>

						<div class="inside">
							<ul>
								<li>
									<div>
										<input type="checkbox" name="antispam_bee_flag_spam" id="antispam_bee_flag_spam" value="1" <?php checked($this->get_option('flag_spam'), 1) ?> />
										<label for="antispam_bee_flag_spam">
											<?php esc_html_e('Mark as Spam, do not delete', 'antispam_bee') ?> <?php $this->show_help_link('flag_spam') ?>
										</label>
									</div>

									<div class="shift <?php echo ($this->get_option('flag_spam') ? '' : 'inact') ?>">
										<ul>
											<li>
												<input type="checkbox" name="antispam_bee_ignore_filter" id="antispam_bee_ignore_filter" value="1" <?php checked($this->get_option('ignore_filter'), 1) ?> />
												<?php esc_html_e('Limit on', 'antispam_bee') ?> <select name="antispam_bee_ignore_type"><?php foreach(array(1 => 'Comments', 2 => 'Pings') as $key => $value) {
													echo '<option value="' .esc_attr($key). '" ';
													selected($this->get_option('ignore_type'), $key);
													echo '>' .esc_html__($value). '</option>';
												} ?>
												</select> <?php $this->show_help_link('ignore_filter') ?>
											</li>
											<li>
												<input type="checkbox" name="antispam_bee_cronjob_enable" id="antispam_bee_cronjob_enable" value="1" <?php checked($this->get_option('cronjob_enable'), 1) ?> />
												<?php echo sprintf(esc_html__('Spam will be automatically deleted after %s days', 'antispam_bee'), '<input type="text" name="antispam_bee_cronjob_interval" value="' .esc_attr($this->get_option('cronjob_interval')). '" class="small-text" />') ?>&nbsp;<?php $this->show_help_link('cronjob_enable') ?>
												<?php if ( $this->get_option('cronjob_enable') && $this->get_option('cronjob_timestamp') ) {
													echo sprintf(
														'<br />(%s @ %s)',
														esc_html__('Last check', 'antispam_bee'),
														date_i18n('d.m.Y H:i:s', ($this->get_option('cronjob_timestamp') + get_option('gmt_offset') * 60))
													);
												} ?>
											</li>
											<li>
												<input type="checkbox" name="antispam_bee_no_notice" id="antispam_bee_no_notice" value="1" <?php checked($this->get_option('no_notice'), 1) ?> />
												<label for="antispam_bee_no_notice">
													<?php esc_html_e('Hide the &quot;MARKED AS SPAM&quot; note', 'antispam_bee') ?> <?php $this->show_help_link('no_notice') ?>
												</label>
											</li>
											<li>
												<input type="checkbox" name="antispam_bee_email_notify" id="antispam_bee_email_notify" value="1" <?php checked($this->get_option('email_notify'), 1) ?> />
												<label for="antispam_bee_email_notify">
													<?php esc_html_e('Send an admin email when new spam item incoming', 'antispam_bee') ?> <?php $this->show_help_link('email_notify') ?>
												</label>
											</li>
										</ul>
									</div>
								</li>
							</ul>

							<ul>
								<li>
									<div>
										<input type="checkbox" name="antispam_bee_country_code" id="antispam_bee_country_code" value="1" <?php checked($this->get_option('country_code'), 1) ?> />
										<label for="antispam_bee_country_code">
											<?php esc_html_e('Block comments and pings from specific countries', 'antispam_bee') ?> <?php $this->show_help_link('country_code') ?>
										</label>
									</div>

									<div class="shift <?php echo ($this->get_option('country_code') ? '' : 'inact') ?>">
										<ul>
											<li>
												<label for="antispam_bee_ipinfodb_key">
													IPInfoDB API Key (<a href="http://www.ipinfodb.com/register.php" target="_blank"><?php esc_html_e('get free', 'antispam_bee') ?></a>)
												</label>
												<input type="text" name="antispam_bee_ipinfodb_key" id="antispam_bee_ipinfodb_key" value="<?php echo esc_attr($this->get_option('ipinfodb_key')); ?>" class="maxi-text code" />
											</li>
										</ul>

										<ul>
											<li>
												<label for="antispam_bee_country_black">
													<?php esc_html_e('Blacklist', 'antispam_bee') ?> (<a href="http://www.iso.org/iso/english_country_names_and_code_elements" target="_blank"><?php esc_html_e('iso codes', 'antispam_bee') ?></a>)
												</label>
												<input type="text" name="antispam_bee_country_black" id="antispam_bee_country_black" value="<?php echo esc_attr($this->get_option('country_black')); ?>" class="regular-text code" />
											</li>
											<li>
												&nbsp;
												<br />
												<?php esc_html_e('or', 'antispam_bee') ?>
											</li>
											<li>
												<label for="antispam_bee_country_white">
													<?php esc_html_e('Whitelist', 'antispam_bee') ?> (<a href="http://www.iso.org/iso/english_country_names_and_code_elements" target="_blank"><?php esc_html_e('iso codes', 'antispam_bee') ?></a>)
												</label>
												<input type="text" name="antispam_bee_country_white" id="antispam_bee_country_white" value="<?php echo esc_attr($this->get_option('country_white')); ?>" class="regular-text code" />
											</li>
										</ul>
									</div>
								</li>
							</ul>

							<ul>
								<li>
									<div>
										<input type="checkbox" name="antispam_bee_honey_pot" id="antispam_bee_honey_pot" value="1" <?php checked($this->get_option('honey_pot'), 1) ?> />
										<label for="antispam_bee_honey_pot">
											<?php esc_html_e('Search comment spammers in the Project Honey Pot', 'antispam_bee') ?> <?php $this->show_help_link('honey_pot') ?>
										</label>
									</div>

									<ul class="shift <?php echo ($this->get_option('honey_pot') ? '' : 'inact') ?>">
										<li>
											<label for="antispam_bee_honey_key">
												Honey Pot API Key (<a href="http://www.projecthoneypot.org/httpbl_configure.php" target="_blank"><?php esc_html_e('get free', 'antispam_bee') ?></a>)
											</label>
											<input type="text" name="antispam_bee_honey_key" id="antispam_bee_honey_key" value="<?php echo esc_attr($this->get_option('honey_key')); ?>" class="maxi-text code" />
										</li>
									</ul>
								</li>
							</ul>

							<ul>
								<li>
									<div>
										<input type="checkbox" name="antispam_bee_translate_api" id="antispam_bee_translate_api" value="1" <?php checked($this->get_option('translate_api'), 1) ?> />
										<label for="antispam_bee_translate_api">
											<?php esc_html_e('Allow comments only in certain language', 'antispam_bee') ?> <?php $this->show_help_link('translate_api') ?>
										</label>
									</div>

									<div class="shift <?php echo ($this->get_option('translate_api') ? '' : 'inact') ?>">
										<ul>
											<li>
												<label for="antispam_bee_translate_lang">
													<?php esc_html_e('Language', 'antispam_bee') ?>
												</label>
												<select name="antispam_bee_translate_lang">
													<?php foreach(array('de' => 'German', 'en' => 'English') as $k => $v) { ?>
														<option <?php selected($this->get_option('translate_lang'), $k); ?> value="<?php echo esc_attr($k) ?>"><?php esc_html_e($v, 'antispam_bee') ?></option>
													<?php } ?>
												</select>
											</li>
											<li>
										</ul>
									</div>
								</li>
							</ul>

							<ul>
								<li>
									<input type="checkbox" name="antispam_bee_advanced_check" id="antispam_bee_advanced_check" value="1" <?php checked($this->get_option('advanced_check'), 1) ?> />
									<label for="antispam_bee_advanced_check">
										<?php esc_html_e('Enable stricter inspection for incomming comments', 'antispam_bee') ?> <?php $this->show_help_link('advanced_check') ?>
									</label>
								</li>
								<li>
									<input type="checkbox" name="antispam_bee_spam_ip" id="antispam_bee_spam_ip" value="1" <?php checked($this->get_option('spam_ip'), 1) ?> />
									<label for="antispam_bee_spam_ip">
										<?php esc_html_e('Consider comments which are already marked as spam', 'antispam_bee') ?> <?php $this->show_help_link('spam_ip') ?>
									</label>
								</li>
								<li>
									<input type="checkbox" name="antispam_bee_already_commented" id="antispam_bee_already_commented" value="1" <?php checked($this->get_option('already_commented'), 1) ?> />
									<label for="antispam_bee_already_commented">
										<?php esc_html_e('Do not check if the comment author has already approved', 'antispam_bee') ?> <?php $this->show_help_link('already_commented') ?>
									</label>
								</li>
							</ul>

							<ul>
								<li>
									<input type="checkbox" name="antispam_bee_dashboard_count" id="antispam_bee_dashboard_count" value="1" <?php checked($this->get_option('dashboard_count'), 1) ?> />
									<label for="antispam_bee_dashboard_count">
										<?php esc_html_e('Display blocked comments count on the dashboard', 'antispam_bee') ?> <?php $this->show_help_link('dashboard_count') ?>
									</label>
								</li>
								<?php if ( $this->is_min_php('5.0.2') ) { ?>
									<li>
										<input type="checkbox" name="antispam_bee_dashboard_chart" id="antispam_bee_dashboard_chart" value="1" <?php checked($this->get_option('dashboard_chart'), 1) ?> />
										<label for="antispam_bee_dashboard_chart">
											<?php esc_html_e('Display statistics on the dashboard', 'antispam_bee') ?> <?php $this->show_help_link('dashboard_chart') ?>
										</label>
									</li>
								<?php } ?>
								<li>
									<input type="checkbox" name="antispam_bee_ignore_pings" id="antispam_bee_ignore_pings" value="1" <?php checked($this->get_option('ignore_pings'), 1) ?> />
									<label for="antispam_bee_ignore_pings">
										<?php esc_html_e('Do not check trackbacks / pingbacks', 'antispam_bee') ?> <?php $this->show_help_link('ignore_pings') ?>
									</label>
								</li>
								<li>
									<input type="checkbox" name="antispam_bee_always_allowed" id="antispam_bee_always_allowed" value="1" <?php checked($this->get_option('always_allowed'), 1) ?> />
									<label for="antispam_bee_always_allowed">
										<?php esc_html_e('Comments are also used outside of posts and pages', 'antispam_bee') ?> <?php $this->show_help_link('always_allowed') ?>
									</label>
								</li>
							</ul>

							<p>
								<input type="submit" name="antispam_bee_submit" class="button-primary" value="<?php esc_html_e('Save Changes') ?>" />
							</p>
						</div>
					</div>
					
				</div>
			</form>
		</div>
	<?php }
}


/* Fire! */
new Antispam_Bee();