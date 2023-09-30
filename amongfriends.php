<?php
/*
 * Copyright (c) 2015,2019-2023 Arne Johannessen
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version. See LICENSE for details.
 */

/*
Plugin Name: Among Friends Wordpress-Plugin
Description: Dieses Plugin implementiert das Verhalten der Among Friends–Website.
Author: Arne Johannessen
Version: 1.1.0
Plugin URI: https://github.com/amongfriends-irishmusic/wordpress-plugin
Author URI: https://github.com/johannessen
*/

// known minimum WP version 4.9, only tested with 6.3


#################################

// implement special HTTP status code for former members
// (set custom page field "af-http-status" to "410")

if ( ! is_admin() ) {
	add_action( 'template_redirect', 'AF_http_status' );
}
function AF_http_status() {
	if (get_post_meta(get_the_ID(), 'af-http-status', TRUE) == 410) {
		header('HTTP/1.1 410 Gone');
	}
}


##################################

function AF_secure_http_links( $content ) {
	// avoid protocol-specific links to own site in database
	// (URLs with ":" or "=" in front of them might be query parameters and must not be modified.)
	$content = preg_replace('{(^|[^=:])https?://(?:www|test)\.amongfriends\.de/}', '$1/', $content);
	return $content;
}
add_filter('content_save_pre', 'AF_secure_http_links');


##################################

function AF_wp_dashboard_test() {
	echo '<p><a href="/wp-admin/tools.php?page=af_server_conf">Server-Konfiguration</a> – E-Mail–Adressen und Apache'
	   . '<p><a href="https://github.com/amongfriends-irishmusic">GitHub</a> – Software-Entwicklung: Theme und Plugin';
}
function AF_wp_dashboard_setup () {
	wp_add_dashboard_widget('AF_wp_dashboard_test', 'Among Friends–Website', 'AF_wp_dashboard_test');
}
add_action('wp_dashboard_setup', 'AF_wp_dashboard_setup');


##################################

// debugging aid: stacktrace for deprecated functions
if (defined('WP_DEBUG') && WP_DEBUG) {
	function SB_stacktrace_on_deprecated_function ($trigger_error) {
		if ($trigger_error) {
			// print a stacktrace
			// (it's impossible to find the culprit without one; that WP doesn't handle this by itself is disgraceful I think)
			
			// prepare stacktrace (the PHP default is hardly usable; another disgrace)
			$clipBacktrace = debug_backtrace();
			$clipBacktrace[] = array('function' => '&lt;init>');
			array_unshift($clipBacktrace, array('function' => __FUNCTION__, 'file' => __FILE__, 'line' => __LINE__));
			
			// if we know this WP version's call stack structure, we shave off parts we don't need
			global $wp_version;
			$clipTraceIndexBegin = ('3.4' == preg_replace('|^([0-9]+\.[0-9]+).*|', '$1', $wp_version)) ? 5 : 1;
			
			// convert stacktrace to HTML for output
			$clipTraceHtml = '<ol class=debug_stacktrace>';
			for ($clipTraceIndex = $clipTraceIndexBegin; $clipTraceIndex < count($clipBacktrace); $clipTraceIndex++) {
				$clipTraceHtml .= "\n\t" . '<li>' . @$clipBacktrace[$clipTraceIndex]['class'];
				$clipTraceHtml .= @$clipBacktrace[$clipTraceIndex]['type'];
				$clipTraceHtml .= @$clipBacktrace[$clipTraceIndex]['method'] ? $clipBacktrace['method'] : $clipBacktrace[$clipTraceIndex]['function'];
				if (array_key_exists('file', $clipBacktrace[$clipTraceIndex - 1]) || array_key_exists('line', $clipBacktrace[$clipTraceIndex - 1])) {
					$clipTraceHtml .= ' (' . $clipBacktrace[$clipTraceIndex - 1]['file'];
					$clipTraceHtml .= ':' . $clipBacktrace[$clipTraceIndex - 1]['line'] . ')';
				}
				$clipTraceHtml .= '</li>';
			}
			$clipTraceHtml .= "\n" . '</ol>';
			echo $clipTraceHtml;
		}
		return $trigger_error;
	}
	
	// high order for this filter so that earlier-called plug-ins may disable the error message by passing FALSE as $trigger_error
	add_filter('deprecated_function_trigger_error', 'SB_stacktrace_on_deprecated_function', 20);
}


##################################

// implement AF shortcode
function af_upcoming_performances($atts) {
	if (! isset($atts['category']) || $atts['category'] == 'announcements') {
		unset($atts['category']);
		$atts['post_type'] = 'post';
		$atts['tax_query'] = array(
			'relation' => 'AND',
			array(
				'terms' => array('announcements'),
				'field' => 'slug',
				'taxonomy' => 'category' ),
			array(
				'terms' => array('performances'),
				'field' => 'slug',
				'taxonomy' => 'category',
				'include_children' => FALSE )
		);
		$atts['orderby'] = isset($atts['orderby']) ? $atts['orderby'] : 'date';
		$atts['order'] = isset($atts['order']) ? $atts['order'] : 'ASC';
	}
	elseif ($atts['category'] == 'performances') {
		$atts['orderby'] = isset($atts['orderby']) ? $atts['orderby'] : 'date';
		$atts['order'] = isset($atts['order']) ? $atts['order'] : 'DESC';
	}
	if (array_key_exists('max', $atts) && $atts['max'] > 0) {
		$atts['showposts'] = $atts['max'];
	}
	$atts['ignore_sticky_posts'] = "no";
	// call 'Posts in Page' plugin
	if (! class_exists('ICPagePosts')) {
		trigger_error("'Posts in Page' plugin unavailable", E_USER_WARNING);
		return "[ic_add_posts category='announcements' ignore_sticky_posts='no']";
	}
	$posts = new ICPagePosts($atts);
	$before = '<div class=af_upcoming_performances>';
	$after = '</div>';
	return $before . $posts->output_posts() . $after;
}

// make AF shortcode available (the class may not be necessary)
class AFUpcomingPerformances {
	public function __construct( ) {
		add_shortcode( 'af_upcoming', array( &$this, 'upcoming_performances' ) );
	}
	public function upcoming_performances( $atts ) {
		return af_upcoming_performances( $atts );
	}
}
function init_AFUpcomingPerformances( ) {
	new AFUpcomingPerformances( );
}
add_action( 'plugins_loaded', 'init_AFUpcomingPerformances' );

// make AF shortcode available in category description (used on performances page of the 1.4 theme)
function af_upcoming_performances_category_filter ($description, $category) {
	if (is_admin()) {
		return $description;
	}
	return str_replace('[af_upcoming]', af_upcoming_performances([]), $description);
}
add_filter('category_description', 'af_upcoming_performances_category_filter', 10, 2);


#################################

# disable Emoji stuff (for which Wordpress has a privacy leak as of 5.1)
add_filter( 'emoji_svg_url', '__return_false' );
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

# meta name=generator is something we dislike
remove_action( 'wp_head' , 'wp_generator' );


#################################

# comments are currently disabled on this site, so a Comments feed doesn't make sense
add_filter( 'feed_links_show_comments_feed', '__return_false' );


#################################

# offer config files to user
function AF_server_conf_menu_setup () {
	add_management_page( 'Among Friends: Server-Konfiguration', 'Server-Konfig', 'manage_options', 'af_server_conf', 'AF_server_conf_menu' );
}
function AF_server_conf_menu () {
	$settings = wp_enqueue_code_editor( array(
		'type' => 'text/nginx',
		'codemirror' => array('readOnly'=>'nocursor') )
	);
	if ( FALSE !== $settings ) {
		$settings = wp_json_encode( $settings );
		wp_add_inline_script( 'code-editor', sprintf('wp.codeEditor.initialize( "af-aliases", %s );', $settings) );
		wp_add_inline_script( 'code-editor', sprintf('wp.codeEditor.initialize( "af-siteconf", %s );', $settings) );
		wp_add_inline_script( 'code-editor', sprintf('wp.codeEditor.initialize( "af-htaccess", %s );', $settings) );
	}
	?>
	<h2>Among Friends: Server-Konfiguration</h2>
	<p>Im Folgenden werden die Inhalte einiger wichtiger Konfigurationsdateien für Among Friends gezeigt. Bei Änderungsvorschlägen bitte Kontakt mit Arne aufnehmen.
	<p title='/etc/postfix/virtual (Auszug)'>Aliase im E-Mail–Server:
	<p><textarea id=af-aliases rows=15 cols=30><?php
	$virtual = file_get_contents('/etc/postfix/virtual');
	$header = '## Among Friends';
	$from = strpos($virtual, $header) + strlen($header);
	$to = strpos($virtual, '#####', $from);
	echo substr($virtual, $from, $to - $from);
	?>
## RFC-required / network-related aliases
postmaster@ postmaster
abuse@ root</textarea>
	<p title='/etc/apache2/sites-available/irishmusic-wp.include'>Apache VirtualHost:
	<p><textarea id=af-siteconf rows=15 cols=30><?php
	echo htmlspecialchars(file_get_contents('/etc/apache2/sites-available/irishmusic-wp.include'));
	?></textarea>
	<p title='<?php echo $_SERVER['DOCUMENT_ROOT'] . '/.htaccess'; ?>'>Apache htaccess:
	<p><textarea id=af-htaccess rows=15 cols=30><?php
	echo htmlspecialchars(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/.htaccess'));
	?></textarea>
	<?php
}
add_action('admin_menu', 'AF_server_conf_menu_setup');


#################################

# partial workaround for Gutenberg float/clear issue
function af_plugin_styles () {
	wp_register_style( 'af-plugin', plugin_dir_url(__FILE__) . 'styles.css' );
	if (wp_get_theme()->get_stylesheet() == 'af-gutenberg') {
		// af-gutenberg already includes a fix for this issue, so there's no need to load an extra resource
		return;
	}
	wp_enqueue_style( 'af-plugin' );
}
add_action('wp_enqueue_scripts', 'af_plugin_styles');


#################################

# Disabling Wordpress warnings that tend to be false alarms allows
# us to focus on those warnings that might signify actual issues.
add_filter( 'site_status_tests', function ($tests) {
	# PHP security support is offered by Debian.
	unset( $tests['direct']['php_version'] );
	# Inactive plugins/themes have a smaller risk than active ones.
	# The primary strategy to minimise the attack surface is to minimise
	# the number of *active* plugins/themes. (And auto-update everything.)
	unset( $tests['direct']['plugin_version'] );
	unset( $tests['direct']['theme_version'] );
	# The Among Friends site sees so little traffic that cache plugins provide
	# neglegible benefit, yet they increase the attack surface (see above).
	unset( $tests['direct']['persistent_object_cache'] );
	#unset( $tests['async']['page_cache'] );
	# The REST API availability check tends to complain about internal errors,
	# such as 403s that never show up in any server log. The log-http-requests
	# plugin can be used to see the error code, which typically is
	# rest_cookie_invalid_nonce. This is related to a harmless backup import.
	#unset( $tests['direct']['rest_availability'] );
	return $tests;
}, 10, 1);


?>
