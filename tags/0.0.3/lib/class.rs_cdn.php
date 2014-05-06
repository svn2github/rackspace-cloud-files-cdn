<?php
/**
 * Functions used to connect to the CDN
 */
global $wpdb;

class RS_CDN {

	public $api_settings;
	public $uploads;

	function __construct($custom_settings = null) {
		$this->api_settings = $this->settings($custom_settings);
		$this->uploads = wp_upload_dir();
	}

	/**
	* Setup Cloud Files CDN Settings
	*/
	public static function settings($custom_settings = null){
		$settings = array( 'username' => (isset($custom_settings['username'])) ? $custom_settings['username'] : 'YOUR USERNAME',
			'apiKey' => (isset($custom_settings['apiKey'])) ? $custom_settings['apiKey'] : 'YOUR API KEY',
			'use_ssl' => (isset($custom_settings['use_ssl'])) ? $custom_settings['use_ssl'] : false,
			'container' => (isset($custom_settings['container'])) ? $custom_settings['container'] : 'default',
			'public_url' => null,
			'verified' => false,
			'region' => (isset($custom_settings['region'])) ? $custom_settings['region'] : 'ORD',
			'url' => (isset($custom_settings['url'])) ? $custom_settings['url'] : 'https://identity.api.rackspacecloud.com/v2.0/');

		// Return settings
		return get_option( RS_CDN_OPTIONS, $settings );
	}

	/**
	 *  Openstack Connection Object
	 */
	function connection_object(){
		$api_settings = $this->api_settings;
		$connection = new \OpenCloud\Rackspace(
			$api_settings['url'],
			array(
				'username' => $api_settings['username'],
				'apiKey' => $api_settings['apiKey']
			)
		);
		$cdn = $connection->ObjectStore( 'cloudFiles', $api_settings['region'], 'publicURL' );
		return $cdn;
	}

	/**
	*  Openstack CDN Container Object
	*/
	public function container_object(){
		$api_settings = $this->api_settings;
		$cdn = $this->connection_object();
		$container = $cdn->Container($api_settings['container']);
		return $container;
	}

	/**
	*  Openstack CDN File Object
	*/
	public function file_object($container, $file_path, $file_name = null){
		$file = $container->DataObject();
		$file->SetData( @file_get_contents( $file_path ) );
		$file->name = (isset($file_name) && !is_null($file_name)) ? $file_name : basename( $file_path );
		return $file;
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
			$container = (!is_null($existing_container)) ? $existing_container : $this->container_object();
			$file = $this->file_object($container, $file_path, $file_name);
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

		// Remove attachment from db
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
		$container = $this->container_object();

		// Loop through files and delete
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
?>
