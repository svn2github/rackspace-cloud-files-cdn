<?php
/**
 * Set global variables
 */
global $wpdb;


/**
 * Register session
 */
function register_session(){
    if( !session_id() )
        session_start();
}
add_action('init','register_session');


/**
 * Upload main image and thumbnails to CDN.
 * Remove the local copy
 */
function upload_images($meta_id, $post_id, $meta_key='', $meta_value=''){
    if ($meta_key == '_wp_attachment_metadata') {
    	global $wpdb;

		// Create new CDN instance if it doesn't exist
		$_SESSION['cdn'] = (isset($_SESSION['cdn'])) ? $_SESSION['cdn'] : new RS_CDN();
		$_SESSION['cdn_container'] = (isset($_SESSION['cdn_container'])) ? $_SESSION['cdn_container'] : $_SESSION['cdn']->container_object();

		// Get upload dir
		$upload_dir = wp_upload_dir();

		// Get files to upload
		$files_to_upload = load_files_needing_upload();

		// Upload files
		foreach ($files_to_upload as $cur_file) {
			// Set file name
			$file_name = trim($upload_dir['subdir'], '/').'/'.basename($cur_file);

			// Upload file to CDN, add to file check
			try {
				$_SESSION['cdn']->upload_file($cur_file, $file_name);
			} catch (Exception $exc) {
				$wpdb->query("INSERT INTO ".$wpdb->prefix."rscdn_failed_uploads (path_to_file) VALUES ('".$upload_dir['basedir'].'/'.$file_name."')");
				return false;
				die();
			}

			// Delete file when successfully uploaded
			@unlink($cur_file);
		}
    }
}
add_action('added_post_meta', 'upload_images', 10, 4);
add_action('updated_post_meta', 'upload_images', 10, 4);


/**
 * Delete file from CDN
 */
function remove_cdn_files( $post_id ){
	global $wpdb;

	// Create new CDN instance
	$_SESSION['cdn'] = (isset($_SESSION['cdn'])) ? $_SESSION['cdn'] : new RS_CDN();
	
	// Get attachment metadata so we can delete all attachments associated with this image
	$attachment_metadata = $wpdb->get_results("SELECT meta_value FROM ".$wpdb->prefix."postmeta WHERE post_id = '$post_id' AND meta_key='_wp_attachment_metadata'");
	if ($wpdb->num_rows > 0) {
		// Get all image sizes for attachment
		$all_image_sizes = unserialize($attachment_metadata[0]->meta_value);

		// Get attachment folder name
		$attach_folder = pathinfo($all_image_sizes['file']);
		$attach_folder = ($attach_folder['dirname'] != '') ? trim($attach_folder['dirname'], '/').'/' : '';

		// Add main file to delete request
		$files_to_delete[] = $all_image_sizes['file'];

		// Delete all thumbnails from CDN
		foreach ($all_image_sizes['sizes'] as $cur_img_size) {
			$files_to_delete[] = $attach_folder.basename($cur_img_size['file']);
		}
	}

	// Send batch delete
	$_SESSION['cdn']->delete_files( $files_to_delete );
}
add_action( 'delete_attachment', 'remove_cdn_files');


/**
 * Verify file does not exist so we don't overwrite it.
 * If the file exists, increment the file name.
  */
function verify_filename($filename, $filename_raw = null) {
	global $wpdb;

	// Get CDN information
	$_SESSION['cdn'] = (isset($_SESSION['cdn'])) ? $_SESSION['cdn'] : new RS_CDN();
	$_SESSION['cdn_settings'] = (isset($_SESSION['cdn_settings'])) ? $_SESSION['cdn_settings'] : $_SESSION['cdn']->settings();
	$_SESSION['cdn_url'] = (isset($_SESSION['cdn_settings']['use_ssl'])) ? $_SESSION['cdn']->container_object()->SSLURI() : $_SESSION['cdn']->container_object()->CDNURI();

	// Get file info
	$info = pathinfo($filename);
	$ext  = empty($info['extension']) ? '' : '.' . $info['extension'];

	// Get attachment metadata so we can delete all attachments associated with this image
	$existing_files = $wpdb->get_results("SELECT guid FROM ".$wpdb->prefix."posts WHERE guid LIKE '%".preg_replace('/[0-9]*$/', '', $info['filename'])."%".$info['extension']."'");

	// Check if file exists
	if (count($existing_files) > 0) {
		// File list
		foreach ($existing_files as $cur_file) {
			$my_files[] = basename($cur_file->guid);
		}

		// Loop through files
		$i=1;
		foreach ($my_files as $cur_file) {
			$file_parts = pathinfo($cur_file);

			if ($file_parts['basename'] == basename($filename)) {
				$filename = $file_parts['filename'].'.'.$file_parts['extension'];
				while (in_array($filename, $my_files)) {
					$filename = $file_parts['filename'].$i++.'.'.$file_parts['extension'];
				}
			}
		}
	}

	return basename($filename);
}
add_filter('sanitize_file_name', 'verify_filename', 10, 2);


/**
 * Get a list of the files that need uploaded
 */
function get_files($params) {
	echo json_encode(load_files_needing_upload());
	die();
}
add_action('wp_ajax_get_files', 'get_files');


/**
 * Upload an existing file from the CDN
 */
function upload_existing_file() {
	global $wpdb;

	// Create new CDN instance and get settings
	$_SESSION['cdn'] = (isset($_SESSION['cdn'])) ? $_SESSION['cdn'] : new RS_CDN();
	$_SESSION['cdn_settings'] = (isset($_SESSION['cdn_settings'])) ? $_SESSION['cdn_settings'] : $_SESSION['cdn']->settings();

	// Get file to upload
	$file_to_upload = $_REQUEST['file_path'];

	// Check if file exists, fail if not
	if (!file_exists($file_to_upload)) {
		echo json_encode(array('response' => 'error', 'message' => 'Upload for "'.basename($file_to_upload).'" failed (001).'));
		die();
	}

	// Get upload dir
	$upload_dir = wp_upload_dir();

	// Try to upload file
	try {
		// Try to upload file
		$_SESSION['cdn']->upload_file($file_to_upload, str_replace($upload_dir['basedir'].'/', '', $file_to_upload));
	} catch (Exception $exc) {
		// Let the browser know upload failed
		echo json_encode(array('response' => 'error', 'message' => 'Upload for "'.basename($file_to_upload).'" failed (001).'));
		die();
	}

	// Verify file was successfully uploaded
	if (verify_successful_upload($file_to_upload) == true) {
		@unlink($file_to_upload);
	}

	// Let the browser know upload was successful
	echo json_encode(array('response' => 'success', 'file_path', $file_to_upload));
	die();
}
add_action('wp_ajax_upload_existing_file', 'upload_existing_file');


/**
 *	Set CDN path for image
  */
function set_cdn_path($attachment) {
	// Get CDN object and settings
	$_SESSION['cdn'] = (isset($_SESSION['cdn'])) ? $_SESSION['cdn'] : new RS_CDN();
	$_SESSION['cdn_settings'] = (isset($_SESSION['cdn_settings'])) ? $_SESSION['cdn_settings'] : $_SESSION['cdn']->settings();

	// Uploads folder data
	$upload_data = wp_upload_dir();

	// Get public CDN URL
	try {
		if (!isset($_SESSION['cdn_url']) || is_null($_SESSION['cdn_url']) || trim($_SESSION['cdn_url']) == '') {
			$_SESSION['cdn_url'] = (isset($_SESSION['cdn_settings']['use_ssl'])) ? $_SESSION['cdn']->container_object()->SSLURI() : $_SESSION['cdn']->container_object()->CDNURI();
		}
	} catch (Exception $e) {
		return $attachment;
	}

	// Rewrite URLs
	if (current_filter() == 'wp_get_attachment_url') {
		if (file_exists(str_replace($upload_data['baseurl'], $upload_data['basedir'], $attachment)) !== false) {
			return $attachment;
		} else {
			return str_replace($upload_data['baseurl'], $_SESSION['cdn_url'], $attachment);
		}
	} else {
		preg_match_all('/\"(http|https).*?\/wp\-content\/.*?\/\d{4}+\/\d{2}+\/.*?\"/i', $attachment, $attachments);

		foreach ($attachments[0] as $cur_attachment) {
			// If local file does not exist, replace local URL with CDN URL
			$cur_attachment = trim($cur_attachment, '"');
			if (file_exists(str_replace($upload_data['baseurl'], $upload_data['basedir'], $cur_attachment)) === false) {
				$new_attachment = str_replace($upload_data['baseurl'], $_SESSION['cdn_url'], $cur_attachment);
				$attachment = str_replace($cur_attachment, $new_attachment, $attachment);
			}
		}

		return $attachment;
	}
	die();
}
add_filter('the_content', 'set_cdn_path');
add_filter('richedit_pre', 'set_cdn_path');
add_filter('wp_get_attachment_url', 'set_cdn_path');


/**
 * Get all files on local disk that need uploaded
 */
function load_files_needing_upload() {
	// Get CDN object and settings
	$_SESSION['cdn'] = (isset($_SESSION['cdn'])) ? $_SESSION['cdn'] : new RS_CDN();
	$_SESSION['cdn_settings'] = (isset($_SESSION['cdn_settings'])) ? $_SESSION['cdn_settings'] : $_SESSION['cdn']->settings();
	$_SESSION['cdn_url'] = (isset($_SESSION['cdn_settings']['use_ssl'])) ? $_SESSION['cdn']->container_object()->SSLURI() : $_SESSION['cdn']->container_object()->CDNURI();

	// Array to store files needing removed
	$files_needing_upload = array();

	// Get uploads directory
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];

	// Setup directory iterator
	$files = new RecursiveIteratorIterator(
	    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
	);

	// Loop through files and find out if they need uploaded
	$i = 0;
	foreach ($files as $fileinfo) {
		$file_path = pathinfo($fileinfo->getRealPath());
	    if (!is_dir($fileinfo->getRealPath())) {
	    	if (isset($_SESSION['cdn_settings']['files_to_ignore'])) {
	    		$ignore_files = explode(",", $_SESSION['cdn_settings']['files_to_ignore']);
		    	if (!in_array($file_path['extension'], $ignore_files)) {
			    	$files_needing_upload['file_'.$i++] = $fileinfo->getRealPath();
		    	}
	    	}
	    }
	}

	return $files_needing_upload;
}


/**
 *	Verify file was uploaded
 */
function verify_successful_upload( $file_path ) {
	// Get CDN object and settings
	$_SESSION['cdn'] = (isset($_SESSION['cdn'])) ? $_SESSION['cdn'] : new RS_CDN();
	$_SESSION['cdn_settings'] = (isset($_SESSION['cdn_settings'])) ? $_SESSION['cdn_settings'] : $_SESSION['cdn']->settings();
	$_SESSION['cdn_url'] = (isset($_SESSION['cdn_settings']['use_ssl'])) ? $_SESSION['cdn']->container_object()->SSLURI() : $_SESSION['cdn']->container_object()->CDNURI();

	// Define variables needed
	$upload_dir = wp_upload_dir();

	// Set CDN URL
	$file_url = str_replace($upload_dir['basedir'], $_SESSION['cdn_url'], $file_path);

	// Setup CURL request
	$curl = curl_init( $file_url );

	// Issue a HEAD request and follow any redirects.
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl, CURLOPT_HEADER, TRUE);
	curl_setopt($curl, CURLOPT_NOBODY, TRUE);

	// Submit request and grab Content-Length header
	$data = curl_exec($curl);
	$remote_file_size = curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
	$local_file_size = filesize($file_path);

	// Close CURL request and return successful upload or not
	@curl_close($curl);
	return ($remote_file_size == $local_file_size) ? true : false;
}


/**
 *	Add download link for all file(s)
 */
/*
function show_download_link($actions, $post) {
	// Relative path/name of the file
	$the_file = str_replace(WP_CONTENT_URL, '.', $post->guid);

	// adding the Action to the Quick Edit row
	$actions['Download'] = '<a href="'.WP_CONTENT_URL.'/download.php?file='.$the_file.'">Download</a>';

	return $actions;    
}
add_filter('media_row_actions', 'show_download_link', 10, 2);
*/
?>