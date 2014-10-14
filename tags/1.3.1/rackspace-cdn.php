<?php
/*
Plugin Name: Rackspace CDN
Plugin URI: http://www.paypromedia.com/
Description: This plugin stores WordPress media files on, and delivers them from, the Rackspace CDN.
Version: 1.3.1
Contributors: bstump
Author URI: http://www.paypromedia.com/individuals/bobbie-stump/
License: GPLv2
*/

defined('WP_PLUGIN_URL') or die('Restricted access');


/**
 *  Require scripts and libraries
 */
require_once("lib/functions.php");
require_once("admin/functions.php");
require_once("lib/class.rs_cdn.php");
if( !class_exists("OpenCloud") ) {
	require_once("lib/php-opencloud-1.5.10/lib/php-opencloud.php");
}


/**
 *  Define constants
 */
define('RS_CDN_PATH', ABSPATH.PLUGINDIR.'/rackspace-cloud-files-cdn/');
define('RS_CDN_URL', WP_PLUGIN_URL.'/rackspace-cloud-files-cdn/');
define('RS_CDN_OPTIONS', "wp_rs_cdn_settings" );


/**
 * Define globals
 */
global $wpdb;


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
	$cdn_settings->remove_local_files = false;
	$cdn_settings->verified = false;
	$cdn_settings->custom_cname = null;
	$cdn_settings->region = 'ORD';
	$cdn_settings->url = 'https://identity.api.rackspacecloud.com/v2.0/';
	add_option( RS_CDN_OPTIONS, $cdn_settings, '', 'yes' );

    // If CDN var is already set, unset it
    unset($_SESSION['cdn']);
}
register_activation_hook( __FILE__, 'rscdn_install' );


/**
 *  Run when plugin is uninstalled
 */
function rscdn_uninstall() {
	global $wpdb;

	// Delete single site option
	@delete_option( RS_CDN_OPTIONS );

	// Delete multisite option
	@delete_site_option( RS_CDN_OPTIONS );

	// Delete failed uploads table
	$wpdb->query( "DROP TABLE IF EXISTS ".$wpdb->prefix."rscdn_failed_uploads" );

    // Remove session
    unset($_SESSION['cdn']);
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