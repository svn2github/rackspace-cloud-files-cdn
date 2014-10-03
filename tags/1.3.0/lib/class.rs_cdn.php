<?php
/**
 * Functions used to connect to the CDN
 */
 
/**
 * Include global vars
 */
global $wpdb;


/**
 * CDN class
 */
class RS_CDN {


	public $oc_connection;
	public $oc_container;
	public $cdn_url;
	public $api_settings;
	public $uploads;


	/**
	 *  Create new Openstack Object
	 */
	function __construct($custom_settings = null, $oc_version = null) {
		// Get settings, if they exist
		(object) $custom_settings = (!is_null($custom_settings)) ? $custom_settings : get_option( RS_CDN_OPTIONS );

		// Set settings
		$settings = new stdClass();
		$settings->username = (isset($custom_settings->username)) ? $custom_settings->username : 'Username';
		$settings->apiKey = (isset($custom_settings->apiKey)) ? $custom_settings->apiKey : 'API Key';
		$settings->use_ssl = (isset($custom_settings->use_ssl)) ? $custom_settings->use_ssl : false;
		$settings->container = (isset($custom_settings->container)) ? $custom_settings->container : 'default';
		$settings->cdn_url = (isset($custom_settings->cdn_url)) ? $custom_settings->cdn_url : null;
		$settings->files_to_ignore = (isset($custom_settings->files_to_ignore)) ? $custom_settings->files_to_ignore : null;
		$settings->remove_local_files = (isset($custom_settings->remove_local_files)) ? $custom_settings->remove_local_files : false;
		$settings->custom_cname = (isset($custom_settings->custom_cname)) ? $custom_settings->custom_cname : null;
		$settings->region = (isset($custom_settings->region)) ? $custom_settings->region : 'ORD';
		$settings->url = (isset($custom_settings->url)) ? $custom_settings->url : 'https://identity.api.rackspacecloud.com/v2.0/';

		// Set API settings
		$this->api_settings = (object) $settings;

		// Set container object
		try {
    		$the_container_obj = $this->container_object();

            // Assign container object
            $this->oc_container = $the_container_obj;
		} catch (Exception $exc) {
    		return false;
		}
	}


	/**
	 *  Openstack Connection Object
	 */
	function connection_object(){
		// If connection object is already set, return it
		if (isset($this->oc_connection)) {
			// Return existing connection object
			return $this->oc_connection;
		}

		// Get settings
		$api_settings = $this->api_settings;

        // Create connection object
		$connection = new \OpenCloud\Rackspace(
			$api_settings->url,
			array(
				'username' => $api_settings->username,
				'apiKey' => $api_settings->apiKey
			)
		);

        // Try to create connection object
        try {
            $cdn = $connection->ObjectStore( 'cloudFiles', $api_settings->region, 'publicURL' );
            $this->oc_connection = $cdn;
            return $this->oc_connection;
        } catch (Exception $exc) {
            $this->oc_connection = null;
            return null;
        }
	}


	/**
	*  Retrieve Openstack CDN Container Object
	*/
	public function container_object() {
	    // If container object is already set, return it
		if (isset($this->oc_container)) {
			// Return existing container
			return $this->oc_container;
		}

		// Get settings
		$api_settings = $this->api_settings;

        // Check if connection object is valid
        if (is_null($this->connection_object())) {
            return null;
        }

		// Setup container
		try {
			// Try to set container
			$this->oc_container = $this->connection_object()->Container($api_settings->container);

            // Return container
    		return $this->oc_container;
		} catch (Exception $exc) {
			$this->oc_container = null;
			return null;
		}
	}


	/**
	*  Create Openstack CDN File Object
	*/
	public function file_object($container, $file_path, $file_name = null){
		// Get file content
		$file_contents = @file_get_contents( $file_path );
		$file_name = (isset($file_name) && !is_null($file_name)) ? $file_name : basename( $file_path );

		// Create file object
		$file = $container->DataObject();
		$file->SetData( $file_contents );
		$file->name = $file_name;
		return $file;
	}


	/**
	* Uploads given file attachment to CDN
	*/
	public function upload_file( $file_path , $file_name = null, $existing_container = null, $post_id = null){
		global $wpdb;

		// Check if file exists
		$check_file_name = (isset($file_name)) ? $file_name : basename($file_path);

		// Get ready to upload file to CDN
		$container = $this->container_object();
		$file = $this->file_object($container, $file_path, $file_name);

		// Upload object
		$content_type = get_content_type( $file_path );
		if ($content_type !== false) {
			if ($file->Create(array('content_type' => $content_type))) {
				return true;
			}
		} else {
			if ($file->Create()) {
				return true;
			}
		}

		// Upload failed, remove local images
		if (stripos($file_path, 'http') == 0) {
			$upload_dir = wp_upload_dir();
			$file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_path);
			unlink($file_path);
		} else {
			unlink($file_path);
		}

		// Upload failed, remove attachment from db
		if (isset($post_id)) {
			$wpdb->query("DELETE FROM $wpdb->posts WHERE ID='$post_id' AND post_type='attachment'");
			$wpdb->query("DELETE FROM $wpdb->postmeta WHERE post_id='$post_id'");
		}

		return false;
	}


	/**
	*  Get list of CDN objects
	*/
	public function get_cdn_objects( $force_cache = false ) {
		// Ensure CDN instance exists
		if (check_cdn() === false) {
			return array();
		}

	    // Path to cache file
	    $cache_file_path = RS_CDN_PATH.'object_cache';

		// Set array to store CDN objects
		$cdn_objects = array();

        // Check if caching is enabled
        if ($force_cache === true || !is_writable(RS_CDN_PATH) || !is_writable($cache_file_path)) {
    		// Update object cache
            try {
                $objects = $this->container_object()->objectList();
            } catch (Exception $exc) {
                return array();
            }

            // Setup objects
            $cdn_objects = array();
            foreach ($objects as $object) {
                $cdn_objects[] = array('fn' => $object['name'], 'fs' => $object['bytes']);
            }

            // Write files to cache file
            if (is_writable(RS_CDN_PATH) && is_writable($cache_file_path)) {
                // Write to cache file
                file_put_contents($cache_file_path, json_encode($cdn_objects));
            }

    		// Return CDN objects
    		return $cdn_objects;
        } else {
            // Return caching
            return json_decode(file_get_contents($cache_file_path), true);
        }
	}


	/**
	 * Force CDN object cache
	 */
	public function force_object_cache() {
		$this->get_cdn_objects( true );
	}


	/**
	* Removes given file attachment(s) from CDN
	*/
	public function delete_files( $files ) {
		// Get container object
		$container = $this->container_object();

		// Delete object(s)
		if (count($files) > 0) {
			foreach ($files as $cur_file) {
				if (trim($cur_file) == '') {
					continue;
				}
				try {
					$file = $container->DataObject();
					$file->name = $cur_file;
					try {
						@$file->Delete();
					} catch (Exception $exc) {
						// Do nothing
					}
				} catch (Exception $exc) {
					// Do nothing
				}
			}

            // Force CDN cache because we removed files
            $this->force_object_cache();
		}
		return true;
	}
}
?>