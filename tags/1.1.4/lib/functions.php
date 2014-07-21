<?php
/**
 * Set global variables
 */
global $wpdb;


/**
 * Register session
 */
function register_session() {
    if( !session_id() )
        session_start();
}
add_action('init','register_session');


/**
 * Ensure CDN instance exists
 */
function check_cdn() {
	// Verify class has been loaded
	if (!class_exists('RS_CDN', true)) {
		require_once("class.rs_cdn.php");
	}

	// Check if CDN exists
	try {
		$_SESSION['cdn'] = (isset($_SESSION['cdn']) && is_object($_SESSION['cdn'])) ? $_SESSION['cdn'] : new RS_CDN();
	} catch (Exception $exc) {
		return false;
	}

	// Session created successfully
	return true;
}

/**
 * Upload main image and thumbnails to CDN.
 * Remove the local copy if user specified in settings.
 */
function upload_images($meta_id, $post_id, $meta_key='', $meta_value='') {
    // Check attachment metadata
    if ($meta_key == '_wp_attachment_metadata') {
    	// Ensure CDN instance exists
		if (check_cdn() === false) {
			return false;
			die();
		}

		// Get upload dir
		$upload_dir = wp_upload_dir();

		// Get files to upload
		$files_to_upload = get_files_to_sync();

		// Add original file to array
		$files_to_upload['upload'][] = array('file_name' => $meta_value['file']);

		// Upload files
		foreach ($files_to_upload['upload'] as $cur_file) {
			// Set file name
			$cur_file_data = $cur_file;
			$cur_file = $upload_dir['basedir'].'/'.$cur_file_data['file_name'];
			$file_name = $cur_file_data['file_name'];

			// Upload file to CDN, add to file check
			try {
				$_SESSION['cdn']->upload_file($cur_file, $file_name);
			} catch (Exception $exc) {
				return false;
				die();
			}

			// Delete file when successfully uploaded, if set
			if (isset($_SESSION['cdn']->api_settings->remove_local_files) && $_SESSION['cdn']->api_settings->remove_local_files == true) {
				@unlink($cur_file);
			}
		}
    }

	// Check attached file meta
    if ($meta_key == '_wp_attached_file') {
    	// Ensure CDN instance exists
		if (check_cdn() === false) {
			return false;
			die();
		}

		// Get upload dir
		$upload_dir = wp_upload_dir();

		$cur_file = $upload_dir['basedir'].'/'.$meta_value;
		$file_name = $meta_value;
		$content_type = get_content_type($cur_file);

		// Upload file to CDN, add to file check
		try {
			$_SESSION['cdn']->upload_file($cur_file, $file_name);
		} catch (Exception $exc) {
			return false;
			die();
		}

		// Delete file when successfully uploaded, if set
		if (isset($_SESSION['cdn']->api_settings->remove_local_files) && $_SESSION['cdn']->api_settings->remove_local_files == true) {
			
			if (stripos($content_type, 'image') === false) {
			    @unlink($cur_file);
		    }
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

	// Ensure CDN instance exists
	if (check_cdn() === false) {
		return false;
	}
	
	// Get attachment metadata so we can delete all attachments associated with this image
	$attachment_metadata = $wpdb->get_results("SELECT meta_key,meta_value FROM ".$wpdb->prefix."postmeta WHERE post_id = '$post_id' AND (meta_key='_wp_attachment_metadata' OR meta_key='_wp_attached_file')");
	if ($wpdb->num_rows > 0) {
		// Check if meta value or attached file
		if ($attachment_metadata[0]->meta_key == '_wp_attached_file') {
			foreach ($attachment_metadata as $cur_attachment_metadata) {
				$files_to_delete[] = $cur_attachment_metadata->meta_value;
			}
		} else {
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

	// Ensure CDN instance exists
	if (check_cdn() === false) {
		return $filename;
	}

	// Get CDN information
	if (isset($_SESSION['cdn']->api_settings->custom_cname) && trim($_SESSION['cdn']->api_settings->custom_cname) != '') {
		 $cdn_url = $_SESSION['cdn']->api_settings->custom_cname;
	} else {
		$cdn_url = (isset($_SESSION['cdn']->api_settings->use_ssl)) ? get_cdn_url('ssl') : get_cdn_url();
	}

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
	// Ensure CDN instance exists
	if (check_cdn() === false) {
		return array('response' => 'fail', 'message' => 'Error instantiating CDN session.');
	}

	$arr_files_to_sync = get_files_to_sync();
	$arr_files_to_sync = array_merge($arr_files_to_sync['upload'], $arr_files_to_sync['download']);
	echo json_encode($arr_files_to_sync);
	die();
}
add_action('wp_ajax_get_files', 'get_files');


/**
*  Get list of files to sync
*/
function get_files_to_sync() {
	// Array to store files needing upload/download
	$objects_to_upload = array();
	$objects_to_download = array();

	// Ensure CDN instance exists
	if (check_cdn() === false) {
		return array('response' => 'fail', 'message' => 'Error instantiating CDN session.', 'upload' => $objects_to_upload, 'download' => $objects_to_download);
	}

	// Get CDN objects
	$local_objects = get_local_files();
	$remote_objects = get_cdn_objects();

	// If CDN objects is null, we need to return an error because we couldn't fetch them
	if (is_null($remote_objects)) {
		return array('response' => 'error', 'message' => 'Unable to retrieve files.');
	}

	// Check local files needing UPloaded
	foreach ($local_objects as $cur_local_object) {
		if (!in_array($cur_local_object, $remote_objects) && $cur_local_object['file_size'] > 0) {
			$objects_to_upload[] = $cur_local_object;
		}
	}

	// Check remote files needing DOWNloaded
	foreach ($remote_objects as $cur_remote_object) {
		if (!in_array($cur_remote_object, $local_objects) && $cur_remote_object['file_size'] > 0) {
			$cdn_url = (isset($_SESSION['cdn']->api_settings->use_ssl)) ? get_cdn_url('ssl') : get_cdn_url();
			$cur_remote_object['file_name'] = $cdn_url.'/'.$cur_remote_object['file_name'];
			$objects_to_download[] = $cur_remote_object;
		}
	}

	// Return array of files that need synchronized
	return array('upload' => $objects_to_upload, 'download' => $objects_to_download);
}


/**
*  Get list of Openstack CDN objects
*/
function get_cdn_objects() {
	// Ensure CDN instance exists
	if (check_cdn() === false) {
		return array('response' => 'fail', 'message' => 'Error instantiating CDN session.');
	}

	// Set array to store CDN objects
	$cdn_objects = array();

	// Get objects
	if ($_SESSION['cdn']->opencloud_version == '1.10.0') {
		$oc_service = $_SESSION['cdn']->opencloud_client()->objectStoreService('cloudFiles', $_SESSION['cdn']->api_settings->region);
		$objects = $oc_service->getContainer($_SESSION['cdn']->api_settings->container)->objectList();
		foreach ($objects as $object) {
			$cdn_objects[] = array('file_name' => $object->getName(), 'file_size' => $object->getContentLength());
		}
	} else {
		$files = $_SESSION['cdn']->container_object()->objectList();
		while ($file = $files->next()) {
			$cdn_objects[] = array('file_name' => $file->name, 'file_size' => $file->bytes);
		}
	}

	// Return CDN objects
	return $cdn_objects;
}


/**
 * Get a list of the files that need uploaded
 */
function get_files_to_remove($params) {
	echo json_encode(get_local_files());
	die();
}
add_action('wp_ajax_get_files_to_remove', 'get_files_to_remove');


/**
 * Sync existing local file to CDN
 */
function sync_existing_file() {
	// Ensure CDN instance exists
	if (check_cdn() === false) {
		echo json_encode(array('response' => 'fail', 'message' => 'Error instantiating CDN session.'));
		die();
	}

	// Upload file - Get file to upload
	$upload_dir = wp_upload_dir();
	$file_to_sync = $_REQUEST['file_path'];

	// Check if file needs uploaded or downloaded
	if (stripos($file_to_sync, 'http') === false) {
		// Upload file, prepend local file path
		$file_to_sync = $upload_dir['basedir'].'/'.$file_to_sync;

		// Check if file exists, fail if not
		if (!file_exists($file_to_sync)) {
			echo json_encode(array('response' => 'error', 'message' => 'Upload for "'.basename($file_to_sync).'" failed (SEF-001).'));
			die();
		}

		// Get upload dir
		$upload_dir = wp_upload_dir();

		// Try to upload file
		try {
			// Try to upload file
			$_SESSION['cdn']->upload_file($file_to_sync, str_replace($upload_dir['basedir'].'/', '', $file_to_sync));
		} catch (Exception $exc) {
			// Let the browser know upload failed
			echo json_encode(array('response' => 'error', 'message' => 'Upload for "'.basename($file_to_sync).'" failed. Exception: '.$exc.' (SEF-002).'));
			die();
		}

		// Verify file was successfully uploaded
		if (isset($_SESSION['cdn']->api_settings->remove_local_files) && $_SESSION['cdn']->api_settings->remove_local_files == true) {
			if (verify_exists($file_to_sync) == true) {
				@unlink($file_to_sync);
			}
		}
	} else {
		// Download file - Get CDN URL
		$cdn_url = (isset($_SESSION['cdn']->api_settings->use_ssl)) ? get_cdn_url('ssl') : get_cdn_url();

		// Write file to disk
		$file_info = pathinfo($file_to_sync);
		$remote_file_name = rawurlencode($file_info['filename']);
		file_put_contents(str_replace($cdn_url, $upload_dir['basedir'], $file_to_sync), file_get_contents(str_replace($file_info['filename'], $remote_file_name, $file_to_sync)));
	}

	// Let the browser know upload was successful
	echo json_encode(array('response' => 'success', 'file_path' => $file_to_sync));
	die();
}
add_action('wp_ajax_sync_existing_file', 'sync_existing_file');
add_action('wp_ajax_upload_existing_file', 'sync_existing_file');


/**
 * Remove existing local file, verify it's on the CDN first
 */
function remove_existing_file() {
	// Ensure CDN instance exists
	if (check_cdn() === false) {
		echo json_encode(array('response' => 'error', 'message' => 'Error instantiating CDN session.'));
		die();
	}

	// Upload file - Get file to upload
	$upload_dir = wp_upload_dir();
	$file_to_sync = $_REQUEST['file_path'];

	// Get CDN URL
	$cdn_url = (isset($_SESSION['cdn']->api_settings->use_ssl)) ? get_cdn_url('ssl') : get_cdn_url();

	// Get remote file URL
	$file_info = pathinfo($file_to_sync);
	$remote_file_name = str_replace($file_info['filename'], rawurlencode($file_info['filename']), $file_to_sync);

	// If file is not on the CDN, upload it
	if (verify_exists($cdn_url.'/'.$remote_file_name)) {
		// Headers are good, delete local file
		try {
			// Remove file
			unlink($upload_dir['basedir'].'/'.$file_to_sync);
		} catch (Exception $exc) {
			// Let the browser know file removal failed
			echo json_encode(array('response' => 'error', 'message' => $exc));
			die();
		}
	} else {
		// Try to upload file
		try {
			// Try to upload file
			$_SESSION['cdn']->upload_file($upload_dir['basedir'].'/'.$file_to_sync, $file_to_sync);

			// Successful upload, delete the file
			unlink($upload_dir['basedir'].'/'.$file_to_sync);
		} catch (Exception $exc) {
			// Let the browser know upload failed
			echo json_encode(array('response' => 'error', 'message' => 'Upload for "'.basename($file_to_sync).'" failed (REF-002).'));
			die();
		}
	}

	// Let the browser know upload was successful
	echo json_encode(array('response' => 'success', 'file_path' => $upload_dir['basedir'].'/'.$file_to_sync));
	die();
}
add_action('wp_ajax_remove_existing_file', 'remove_existing_file');


/**
 *	Set CDN path for image
  */
function set_cdn_path($attachment) {
	// Ensure CDN instance exists
	if (check_cdn() === false) {
		return $attachment;
	}

	// Uploads folder data
	$upload_data = wp_upload_dir();

	// Get public CDN URL
	try {
		if (isset($_SESSION['cdn']->api_settings->custom_cname) && trim($_SESSION['cdn']->api_settings->custom_cname) != '') {
			 $cdn_url = $_SESSION['cdn']->api_settings->custom_cname;
		} else {
			$cdn_url = (isset($_SESSION['cdn']->api_settings->use_ssl)) ? get_cdn_url('ssl') : get_cdn_url();
		}
	} catch (Exception $e) {
		return $attachment;
	}

	// Rewrite URLs
	if (current_filter() == 'wp_get_attachment_url') {
		if (file_exists(str_replace($upload_data['baseurl'], $upload_data['basedir'], $attachment)) !== false) {
			$remote_file_url = str_replace($upload_data['baseurl'], $cdn_url, $attachment);
			if (verify_exists($remote_file_url)) {
				return $remote_file_url;
			} else {
				return $attachment;
			}
		} else {
			return str_replace($upload_data['baseurl'], $cdn_url, $attachment);
		}
	} else {
		preg_match_all('/\"(http|https).*?\/wp\-content\/.*?\/\d{4}+\/\d{2}+\/.*?\"/i', $attachment, $attachments);

		foreach ($attachments[0] as $cur_attachment) {
			// If local file does not exist, replace local URL with CDN URL
			$cur_attachment = trim($cur_attachment, '"');
			if (file_exists(str_replace($upload_data['baseurl'], $upload_data['basedir'], $cur_attachment)) === false) {
				$new_attachment = str_replace($upload_data['baseurl'], $cdn_url, $cur_attachment);
				if (verify_exists($new_attachment)) {
					$attachment = str_replace($cur_attachment, $new_attachment, $attachment);
				}
			} else {
				// File exists locally, check if CDN file is there
				$new_attachment = str_replace($upload_data['baseurl'], $cdn_url, $cur_attachment);
				if (verify_exists($new_attachment)) {
					$attachment = str_replace($cur_attachment, $new_attachment, $attachment);
				}
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
 *	Get local files
 */
function get_local_files() {
	// Get uploads directory
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];
	$local_files = array();

	// If uploads directory is not found, tell the user to create it
	if (!is_dir($dir)) {
		return array('response' => 'error', 'message' => 'Directory "'.$dir.'" not found. Please create it.');
	}

	// Setup directory iterator
	$files = new RecursiveIteratorIterator(
	    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
	);

	// Loop through files and find out if they need uploaded
	$i = 0;
	foreach ($files as $fileinfo) {
		$the_file = $fileinfo->getRealPath();
		$file_path = pathinfo($the_file);
	    if (!is_dir($the_file)) {
	    	if (isset($_SESSION['cdn']->api_settings->files_to_ignore)) {
	    		// File extensions ignored
	    		$ignore_files = explode(",", $_SESSION['cdn']->api_settings->files_to_ignore);
		    	if (!in_array($file_path['extension'], $ignore_files)) {
		    		$cur_local_file = $fileinfo->getRealPath();
		    		$local_files[$i++] = array('file_name' => trim(str_replace($upload_dir['basedir'], '', $cur_local_file), '/'), 'file_size' => filesize($cur_local_file));
		    	}
	    	} else {
		    	// No file extensions ignored
		    	$cur_local_file = $fileinfo->getRealPath();
		    	$local_files[$i++] = array('file_name' => trim(str_replace($upload_dir['basedir'], '', $cur_local_file), '/'), 'file_size' => filesize($cur_local_file));
	    	}
	    }
	}
	return $local_files;
}


/**
 *	Verify file exists
 */
function verify_exists( $file_path ) {
	// Ensure CDN instance exists
	if (check_cdn() === false) {
		return false;
	}

	// Get CDN URL
	if (isset($_SESSION['cdn']->api_settings->custom_cname) && trim($_SESSION['cdn']->api_settings->custom_cname) != '') {
		$cdn_url = $_SESSION['cdn']->api_settings->custom_cname;
	} else {
		$cdn_url = (isset($_SESSION['cdn']->api_settings->use_ssl)) ? get_cdn_url('ssl') : get_cdn_url();
	}

	// Define variables needed
	$upload_dir = wp_upload_dir();

	// Set CDN URL
	if (stripos($file_path, $cdn_url) === false) {
		$file_url = $cdn_url.'/'.$file_path;
	} else {
		$file_url = str_replace($upload_dir['basedir'], $cdn_url, $file_path);
	}

	// Verify file exists, use curl if "allow_url_fopen" is not allowed
	if(ini_get('allow_url_fopen')) {
		if (strstr(current(get_headers($file_url)), "200")) {
			return true;
		} else {
			return false;
		}
	} else {
		$ch = curl_init($file_url);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_exec($ch);
		curl_close($ch);
		$return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($return_code == 200) {
			return true;
		} else {
			return false;
		}
	}

	return true;
}


/**
 * Get CDN URL
 */
function get_cdn_url($type = 'http') {
	// Ensure CDN instance exists
	if (check_cdn() === false) {
		$wp_url = wp_upload_dir();
		return $wp_url['baseurl'];
	}

	// Get correct CDN URL
	$type = strtolower($type);
	if ($_SESSION['cdn']->opencloud_version == '1.10.0') {
		if ($type == 'ssl' || $type == 'https') {
			// Return SSL URI
			return $_SESSION['cdn']->container_object()->getCdn()->getCdnSslUri();
		} elseif ($type == 'streaming') {
			// Return Streaming URI
			return $_SESSION['cdn']->container_object()->getCdn()->getCdnStreamingUri();
		} elseif ($type == 'ios-streaming') {
			// Return Streaming URI
			return $_SESSION['cdn']->container_object()->getCdn()->getIosStreamingUri();
		} else {
			// Return HTTP URI
			return $_SESSION['cdn']->container_object()->getCdn()->getCdnUri();
		}			
	} else {
		if ($type == 'ssl' || $type == 'https') {
			// Return SSL URI
			return $_SESSION['cdn']->container_object()->SSLURI();
		} elseif ($type == 'streaming') {
			// Return Streaming URI
			// return $_SESSION['cdn']->container_object()->getCdn()->getCdnStreamingUri();
			return $_SESSION['cdn']->container_object()->CDNURI();
		} elseif ($type == 'ios-streaming') {
			// Return Streaming URI
			// return $_SESSION['cdn']->container_object()->getCdn()->getIosStreamingUri();
			return $_SESSION['cdn']->container_object()->CDNURI();
		} else {
			// Return HTTP URI
			return $_SESSION['cdn']->container_object()->CDNURI();
		}	
	}
}


/**
 * Get content/mime type of file
 */
function get_content_type($file) {
    $type = null;
    if (function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = finfo_file($finfo, $file);
        @finfo_close($finfo);
    }

	// Check file type
    if (!$type || in_array($type, array('application/octet-stream', 'text/plain'))) {
        $secondOpinion = exec('file -b --mime-type ' . escapeshellarg($file), $foo, $returnCode);
        if ($returnCode == 0) {
            return false;
        } else {
	        return $returnCode;
        }
    }

    if (!$type || in_array($type, array('application/octet-stream', 'text/plain'))) {
        require_once 'upgradephp/ext/mime.php';
        $exifImageType = exif_imagetype($file);
        if ($exifImageType !== false) {
            try {
	            $type = image_type_to_mime_type($exifImageType);
            } catch (Exception $exc) {
	            return false;
            }
        }
    }

    return (!is_null($type)) ? $type : false;
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