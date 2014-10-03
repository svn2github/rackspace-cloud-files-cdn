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
}
add_action('admin_menu', 'rs_cdn_admin_pages');


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

		// Turn array of settings into an object
		$cdn_settings = (object) $cdn_settings;

		// Create new CDN instance if auth values have changed, otherwise just update settings
		try {
			// Try to create new instance
			$new_instance = new RS_CDN($cdn_settings);

			// New instance created, save settings
			update_option(RS_CDN_OPTIONS, $cdn_settings);

			// Assign newly created instance to the CDN
			unset($_SESSION['cdn']);
			$_SESSION['cdn'] = $new_instance;

            // Force CDN object cache
			$_SESSION['cdn']->get_cdn_objects(true);
		} catch (Exception $exc) {
			// Exception encountered, return false
			return array('response' => 'error', 'message' => 'The new settings you entered failed authentication, so they were not updated. Please try again.');
		}
	}
}
?>