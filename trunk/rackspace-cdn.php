<?php
/*
Plugin Name: Rackspace CDN
Plugin URI: http://www.paypromedia.com/
Description: This plugin stores WordPress media files on, and delivers them from, the Rackspace CDN.
Version: 1.0.1
Contributors: bstump
Author URI: http://www.paypromedia.com/individuals/bobbie-stump/
License: GPLv2
*/

defined('WP_PLUGIN_URL') or die('Restricted access');

global $wpdb;


/**
 *  Define constants
 */
define('RS_CDN_PATH', ABSPATH.PLUGINDIR.'/rackspace-cloud-files-cdn/');
define('RS_CDN_URL', WP_PLUGIN_URL.'/rackspace-cloud-files-cdn/');
define('RS_CDN_OPTIONS', "wp_rs_cdn_settings" );
define('RS_CDN_LOADIND_URL', WP_PLUGIN_URL.'/rackspace-cloud-files-cdn/assets/images/loading.gif');


/**
 *  Require scripts and libraries
 */
require_once(ABSPATH.'wp-admin/includes/upgrade.php');
require_once("lib/functions.php");
require_once("admin/functions.php");
require_once("lib/class.rs_cdn.php");
if( !class_exists("OpenCloud") ){
	if (version_compare(phpversion(), '5.3.3') >= 0) {
		require_once("lib/php-opencloud-1.9.2/lib/php-opencloud.php");
	} else {
		require_once("lib/php-opencloud-1.5.10/lib/php-opencloud.php");
	}
}


/**
 *  Run when plugin is installed
 */
function rscdn_install() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	global $wpdb;

	// Add default CDN settings
	$cdn_settings = new stdClass();
	$cdn_settings->username = 'Username';
	$cdn_settings->apiKey = 'API Key';
	$cdn_settings->use_ssl = false;
	$cdn_settings->container = 'default';
	$cdn_settings->cdn_url = null;
	$cdn_settings->files_to_ignore = null;
	$cdn_settings->verified = false;
	$cdn_settings->custom_cname = null;
	$cdn_settings->region = 'ORD';
	$cdn_settings->url = 'https://identity.api.rackspacecloud.com/v2.0/';
	add_option( RS_CDN_OPTIONS, $cdn_settings, '', 'yes' );

	// Create table ofr failed uploads
	$failed_uploads = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."rscdn_failed_uploads` (
		`path_to_file` varchar(300) COLLATE utf8_unicode_ci NOT NULL
		PRIMARY KEY (`path_to_file`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
	dbDelta( $failed_uploads );
}
register_activation_hook( __FILE__, 'rscdn_install' );


/**
 *  Run when plugin is uninstalled
 */
function rscdn_uninstall() {
	// Delete single site option
	@delete_option( RS_CDN_OPTIONS );

	// Delete multisite option
	@delete_site_option( RS_CDN_OPTIONS );

	// Delete failed uploads table
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS ".$wpdb->prefix."rscdn_failed_uploads" );
}
register_uninstall_hook( __FILE__, 'rscdn_uninstall' );


/**
 *  Register and enqueue admin JavaScript
 */
function rs_cdn_admin_js() {
	wp_enqueue_script('media-upload');
	wp_enqueue_script('admin-js', RS_CDN_URL.'assets/js/admin.js');
}
add_action('admin_enqueue_scripts', 'rs_cdn_admin_js');
?>