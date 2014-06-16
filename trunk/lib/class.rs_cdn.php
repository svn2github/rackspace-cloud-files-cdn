<?php
/**
 * Functions used to connect to the CDN
 */
global $wpdb;

class RS_CDN {


	public $oc_service;
	public $oc_container;
	public $cdn_url;
	public $opencloud_version;
	public $api_settings;
	public $uploads;


	/**
	 *  Create new Openstack Object
	 */
	function __construct() {
		// Set opencloud version to use
		$this->opencloud_version = (version_compare(phpversion(), '5.3.3') >= 0) ? '1.9.2' : '1.5.10';

		// Get settings, if they exist
		$custom_settings = (object) get_option( RS_CDN_OPTIONS );

		// Set settings
		$settings = new stdClass();
		$settings->username = (isset($custom_settings->username)) ? $custom_settings->username : 'Username';
		$settings->apiKey = (isset($custom_settings->apiKey)) ? $custom_settings->apiKey : 'API Key';
		$settings->use_ssl = (isset($custom_settings->use_ssl)) ? $custom_settings->use_ssl : false;
		$settings->container = (isset($custom_settings->container)) ? $custom_settings->container : 'default';
		$settings->cdn_url = null;
		$settings->files_to_ignore = null;
		$settings->verified = false;
		$settings->custom_cname = null;
		$settings->region = (isset($custom_settings->region)) ? $custom_settings->region : 'ORD';
		$settings->url = (isset($custom_settings->url)) ? $custom_settings->url : 'https://identity.api.rackspacecloud.com/v2.0/';

		// Set API settings
		$this->api_settings = (object) $settings;

		// Return client OR set settings
		if ($this->opencloud_version == '1.9.2') {
			// Set new Cloud Files client
			$cloud_files_client = new \OpenCloud\Rackspace(
				$settings->url,
				(array) $settings
			);

			// Set Rackspace CDN settings
			$this->oc_service = $cloud_files_client->objectStoreService('cloudFiles', $settings->region);

			// Set container object
			$this->oc_container = $this->container_object();
		}
	}


	/**
	 *  Openstack Connection Object
	 */
	function connection_object(){
		if ($this->opencloud_version == '1.9.2') {
			// Return service
			return $this->oc_service;
		} else {
			// Get settings and connection object
			$api_settings = $this->api_settings;
			$connection = new \OpenCloud\Rackspace(
				$api_settings->url,
				array(
					'username' => $api_settings->username,
					'apiKey' => $api_settings->apiKey
				)
			);

			// Return connection object
			$cdn = $connection->ObjectStore( 'cloudFiles', $api_settings->region, 'publicURL' );
			$this->oc_service = $cdn;
			return $cdn;
		}
	}


	/**
	*  Openstack CDN Container Object
	*/
	public function container_object() {
		if ($this->opencloud_version == '1.9.2') {
			$api_settings = $this->api_settings;
			if (!isset($this->oc_container)) {
				try {
					$this->oc_container = $this->oc_service->getContainer($api_settings->container);
				} catch (Exception $exc) {
					$this->oc_container = null;
				}
			}
			return $this->oc_container;
		} else {
			$api_settings = $this->api_settings;
			$cdn = $this->connection_object();
			$container = $cdn->Container($api_settings->container);
			return $container;
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
		if ($this->opencloud_version == '1.9.2') {
			return array('file_name' => $file_name, 'file_content' => $file_contents);
		} else {
			$file = $container->DataObject();
			$file->SetData( $file_contents );
			$file->name = $file_name;
			return $file;
		}
	}


	/**
	* Uploads given file attachment onto CDN if it doesn't already exist
	*/
	public function upload_file( $file_path , $file_name = null, $existing_container = null, $post_id = null){
		global $wpdb;

		// Check if file exists
		$check_file_name = (isset($file_name)) ? $file_name : basename($file_path);
		if (@fopen(set_cdn_path($check_file_name), 'r') !== false) {
			return true;
		} else {
			// Get ready to upload file to CDN
			$container = $this->oc_container;
			$file = $this->file_object($container, $file_path, $file_name);

			// Upload object
			if ($this->opencloud_version == '1.9.2') {
				if ($container->uploadObject($file['file_name'], $file['file_content'])) {
					return true;
				}
			} else {
				if ($content_type = get_content_type( $file_path ) !== false) {
					if ($file->Create(array('content_type' => $content_type))) {
						return true;
					}
				} else {
					if ($file->Create()) {
						return true;
					}
				}
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

		// Remove attachment from db
		if (isset($post_id)) {
			$wpdb->query("DELETE FROM $wpdb->posts WHERE ID='$post_id' AND post_type='attachment'");
			$wpdb->query("DELETE FROM $wpdb->postmeta WHERE post_id='$post_id'");
		}
		return false;
	}


	/**
	* Get mime type of file
	*/
	public function get_content_type($file) {
		if (function_exists("finfo_file")) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime = finfo_file( $finfo , $file );
			finfo_close( $finfo );
			return $mime;
		} elseif ( function_exists( "mime_content_type" ) ) {
			return mime_content_type($file);
		} elseif ( !stristr( ini_get( "disable_functions" ), "shell_exec" ) ) {
			$file = escapeshellarg( $file );
			$mime = shell_exec( "file -bi ".$file );
			return $mime;
		} else {
			return false;
		}
	}


	/**
	* Removes given file attachment(s) from CDN
	*/
	public function delete_files( $files ) {
		// Get container object
		$container = $this->oc_container;

		// Delete object
		if ($this->opencloud_version == '1.9.2') {
			foreach ($files as $cur_file) {
				$file = $container->getObject($cur_file);
				try {
					$file->Delete();
				} catch (Exception $e) {
					return $e;
				}
			}
		} else {
			foreach ($files as $cur_file) {
				$file = $container->DataObject();
				$file->name = $cur_file;
				try {
					$file->Delete();
				} catch (Exception $e) {
					return $e;
				}
			}
			return true;
		}
	}
}
?>
