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


	public $oc_client;
	public $oc_service;
	public $oc_conn_object;
	public $oc_container;
	public $cdn_url;
	public $opencloud_version;
	public $api_settings;
	public $uploads;


	/**
	 *  Create new Openstack Object
	 */
	function __construct($custom_settings = null, $oc_version = null) {
		// Set opencloud version to use
		$this->opencloud_version = (version_compare(phpversion(), '5.3.3') >= 0) ? '1.10.0' : '1.5.10';
		$this->opencloud_version = (!is_null($oc_version)) ? $oc_version : $this->opencloud_version;

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

		// Return client OR set settings
			if ($this->opencloud_version == '1.10.0') {
			// Set Rackspace CDN settings
			$this->oc_client = $this->opencloud_client();
			$this->oc_service = $this->oc_client->objectStoreService('cloudFiles', $settings->region);
		}

		// Set container object
		$this->oc_container = $this->container_object();
	}


	/**
	 * Cloud files client
	 */
	public function opencloud_client() {
		// Set new Cloud Files client
		$cloud_files_client = new \OpenCloud\Rackspace(
			$this->api_settings->url,
			(array) $this->api_settings
		);

		// Set Rackspace CDN settings
		$this->oc_client = $cloud_files_client;
		
		return $cloud_files_client;
	}


	/**
	 *  Openstack Connection Object
	 */
	function connection_object(){
		if ($this->opencloud_version == '1.10.0') {
			// Return service
			return $this->oc_service;
		} else {
			// If connection object is already set, return it
			if (isset($this->oc_service)) {
				return $this->oc_service;
			}

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
	*  Retrieve Openstack CDN Container Object
	*/
	public function container_object() {
		if ($this->opencloud_version == '1.10.0') {
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
			if (isset($this->oc_container)) {
				try {
					$this->connection_object()->Container($api_settings->container);
					return $this->oc_container;
				} catch (Exception $exc) {
					$_SESSION['cdn'] = new RS_CDN();
					return $_SESSION['cdn']->oc_container;
				}
			}
			$cdn = $this->connection_object();
			$container = $cdn->Container($api_settings->container);
			$this->oc_container = $container;
			return $this->oc_container;
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
		if ($this->opencloud_version == '1.10.0') {
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
		if (verify_exists($check_file_name)) {
			return true;
		} else {
			// Get ready to upload file to CDN
			$container = $this->container_object();
			$file = $this->file_object($container, $file_path, $file_name);

			// Upload object
			if ($this->opencloud_version == '1.10.0') {
				if ($container->uploadObject($file['file_name'], $file['file_content'])) {
					return true;
				}
			} else {
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
	* Removes given file attachment(s) from CDN
	*/
	public function delete_files( $files ) {
		// Get container object
		$container = $this->container_object();

		// Delete object
		if ($this->opencloud_version == '1.10.0') {
			foreach ($files as $cur_file) {
				try {
					$file = $container->getObject($cur_file);
					try {
						$file->Delete();
					} catch (Exception $exc) {
						return $exc;
					}
				} catch (Exception $exc) {
					return $e;
				}
			}
		} else {
			foreach ($files as $cur_file) {
				try {
					$file = $container->DataObject();
					$file->name = $cur_file;
					try {
						@$file->Delete();
					} catch (Exception $exc) {
						return $exc;
					}
				} catch (Exception $exc) {
					return $exc;
				}
			}
			return true;
		}
	}
}
?>
