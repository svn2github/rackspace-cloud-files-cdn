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

		// Turn off SSL if custom CNAME is being used
		if (isset($_POST['rs_cdn']['custom_cname']) && trim($_POST['rs_cdn']['custom_cname']) != '') {
			unset($_POST['rs_cdn']['use_ssl']);
		} else {
			unset($_POST['rs_cdn']['custom_cname']);
		}

		// Save settings in database
		$cdn_settings = $_POST['rs_cdn'];
		update_option(RS_CDN_OPTIONS, $cdn_settings);

		// Turn off SSL if custom CNAME is being used
		if (isset($_POST['rs_cdn']['custom_cname']) && trim($_POST['rs_cdn']['custom_cname']) != '') {
			$_SESSION['cdn_url'] = $_POST['rs_cdn']['custom_cname'];
		} else {
			try {
				// Try to create new CDN url
				$_SESSION['cdn'] = new RS_CDN();

				// Try to set CDN URL for session
				$_SESSION['cdn_url'] = (isset($_POST['rs_cdn']['use_ssl'])) ? $_SESSION['cdn']->container_object()->SSLURI() : $_SESSION['cdn']->container_object()->CDNURI();
			} catch (Exception $exc) {
				// Exception
			}
		}

		// Set API settings to the new settings
		$_SESSION['cdn_settings'] = $_SESSION['cdn']->api_settings = $cdn_settings;
	}
}

?>
