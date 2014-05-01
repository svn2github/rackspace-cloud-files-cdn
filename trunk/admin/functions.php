<?php

/**
 *  Require manage.php
 */
function rs_cdn_manage() {
	require_once(RS_CDN_PATH."admin/manage.php");
}


/**
 *  Create admin pages for plugin management.
 */
function rs_cdn_admin_pages() {
	if (current_user_can('manage_options')) {
		add_menu_page("Rackspace CDN", "Rackspace CDN", "publish_posts", "rs-cdn-manage", "rs_cdn_manage");
	}
}add_action('admin_menu', 'rs_cdn_admin_pages');


/**
 * Save CloudFiles CDN Settings
 */
function save_cdn_settings() {
	if (is_admin() && current_user_can('manage_options') && !empty($_POST) && !empty($_POST['rs_cdn'])) {
		$cdn_settings = $_POST['rs_cdn'];
		update_option(RS_CDN_OPTIONS, $cdn_settings);
		$_SESSION['cdn_settings'] = $cdn_settings;
	}
}

?>
