<?php
/*
 * Copyright (c) 2015,2019 Arne Johannessen
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version. See LICENSE for details.
 */

/*
Plugin Name: Among Friends Wordpress-Plugin
Description: Dieses Plugin implementiert verschiedene Details der Among Friends–Website.
Author: Arne Johannessen
Version: 0.5.1
Plugin URI: https://github.com/amongfriends-irishmusic/wordpress-plugin
Author URI: https://github.com/johannessen
*/

// made for Wordpress 4.1, updated for 5.0


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

// used Dashboard code example 'dashboard-google-pagerank' by Weston Deboer
function AF_wp_dashboard_test() {
	echo '<P>Die Textbearbeitung erfolgte <STRONG>vor Gutenberg</STRONG> in <A HREF="http://de.wikipedia.org/wiki/Markdown#Auszeichnungsbeispiele">Markdown</A>-Syntax (<A HREF="http://daringfireball.net/projects/markdown/syntax" HREFLANG="en">Referenz</A>).';
}
function AF_wp_dashboard_setup () {
	wp_add_dashboard_widget('AF_wp_dashboard_test', 'Among Friends–Website', 'AF_wp_dashboard_test');
}
add_action('wp_dashboard_setup', 'AF_wp_dashboard_setup');


##################################

// Transforming XHTML into HTML
// <http://www.robertnyman.com/2006/09/20/how-to-deliver-html-instead-of-xhtml-with-wordpress/>
function SB_wp_xml2html ($buffer) {
	$XML = array(' />');
	$HTML = array('>');
	return str_replace($XML, $HTML, $buffer);
}
function SB_wp_xml2html_ob_start () {
	ob_start('SB_wp_xml2html');
}
add_action('get_header', 'SB_wp_xml2html_ob_start');


##################################

// TODO: make sticky posts and/or announcement posts stand out in performances category



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
		$atts['category'] = 'announcements';
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

# Gutenberg stuff

# to disable embeds:
# https://rudrastyh.com/gutenberg/remove-default-blocks.html
# https://wordpress.org/gutenberg/handbook/designers-developers/developers/filters/block-filters/



?>
