<?php
/*
Plugin Name: Rackspace CDN
Plugin URI: http://www.paypromedia.com/
Description: This plugin stores WordPress media files on, and delivers them from, the Rackspace CDN.
Version: 0.0.1
Contributors: bstump
Author URI: http://www.paypromedia.com/individuals/bobbie-stump/
License: GPLv2

Thanks to richardroyal for the original idea. I used only a very small portion of his code, but
rewrote the entire plugin to be more stable and easier to use, as well as cleaned up some files
that were not being used.
*/

defined('WP_PLUGIN_URL') or die('Restricted access');

global $wpdb;

define('RS_CDN_PATH', ABSPATH.PLUGINDIR.'/rackspace-cdn/');
define('RS_CDN_URL', WP_PLUGIN_URL.'/rackspace-cdn/');
define('RS_CDN_ROUTE', get_bloginfo('url').'/?rs_cdn_routing=');
define('RS_CDN_OPTIONS', "wp_rs_cdn_settings" );
define('RS_CDN_LOADIND_URL', WP_PLUGIN_URL.'/rackspace-cdn/assets/images/loading.gif');

require_once(ABSPATH.'wp-admin/includes/upgrade.php');
require_once("lib/functions.php");
require_once("admin/functions.php");
require_once("lib/class.rs_cdn.php");
if( !class_exists("OpenCloud") ){
	require_once("lib/php-opencloud-1.5.10/lib/php-opencloud.php");
}


/**
 *  Run when plugin is installed
 */
function rscdn_install() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	global $wpdb;

	// Create table ofr failed uploads
	$failed_uploads = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."rscdn_failed_uploads` (
		`path_to_file` varchar(300) COLLATE utf8_unicode_ci NOT NULL
		PRIMARY KEY (`path_to_file`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
	dbDelta( $failed_uploads );
}
register_activation_hook( __FILE__, 'rscdn_install' );


/**
 *  Register and enqueue admin JavaScript
 */
function rs_cdn_admin_js() {
	wp_enqueue_script('jquery');
	wp_enqueue_media();
	wp_enqueue_style('thickbox');
	wp_enqueue_script('media-upload');
	wp_enqueue_script('thickbox');
	wp_enqueue_script('admin-js', RS_CDN_URL.'assets/js/admin.js');
}
add_action('admin_enqueue_scripts', 'rs_cdn_admin_js');


/**
 *  Plugin routing
 */
function rs_cdn_parse_query_vars($vars) {
	$vars[] = 'rs_cdn_routing';
	return $vars;
}
add_filter('query_vars', 'rs_cdn_parse_query_vars');
?>
