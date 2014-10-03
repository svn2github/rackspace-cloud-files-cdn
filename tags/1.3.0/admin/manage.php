<?php
	/**
	 * Start session, if not started
	 */
	if( !session_id() )
		session_start();


	/**
	 * Make sure CDN constants are defined
	 */
	defined('RS_CDN_PATH') or die();


	/**
	 * Set error to false
	 */
	$settings_error = false;
	$show_errors = array();


	/**
	 * Save CDN settings
	 */
	if (isset($_POST['save_cdn_settings'])) {
		try {
			$save_settings = save_cdn_settings();

			// See if save was successful
			if (isset($save_settings['response']) && $save_settings['response'] == 'error') {
				$show_errors[] = $save_settings['message'];
			}
		} catch (Exception $exc) {
			$settings_error = true;
		}
	}


	/**
	 * Try to create a CDN instance
	 */
	try {
		// Create new instance
		if (check_cdn() == false) {
			$show_errors[] = 'Could not create instance of class RS_CDN.';
		}

		// Check if connection has been made by grabbing container
		if (!isset($_SESSION['cdn']) || !is_object($_SESSION['cdn']) || is_null($_SESSION['cdn']) || is_null($_SESSION['cdn']->container_object())) {
			$show_errors[] = 'Container does not exist.';
		}
	} catch (Exception $exc) {
		if (stripos($exc, 'Unauthorized') !== false) {
			$show_errors[] = 'Unable to connect to the CDN, please check the credentials below.';
		} else {
			$show_errors[] = $exc;
		}
	}


	/**
	 * Assign API settings
	 */
	if (!isset($_SESSION['cdn']->api_settings)) {
		if ( get_option( RS_CDN_OPTIONS ) == false ) {
			// Add default CDN settings
			$cdn_settings = new stdClass();
			$cdn_settings->username = 'Username';
			$cdn_settings->apiKey = 'API Key';
			$cdn_settings->use_ssl = false;
			$cdn_settings->container = 'default';
			$cdn_settings->files_to_ignore = null;
			$cdn_settings->remove_local_files = false;
			$cdn_settings->custom_cname = null;
			$cdn_settings->region = 'ORD';
			$cdn_settings->url = 'https://identity.api.rackspacecloud.com/v2.0/';
		} else {
			$cdn_settings = (object) get_option( RS_CDN_OPTIONS );
			$cdn_settings->url = (isset($cdn_settings->url)) ? $cdn_settings->url : 'https://identity.api.rackspacecloud.com/v2.0/';
		}
	} else {
		$cdn_settings = (object) $_SESSION['cdn']->api_settings;
	}


	/**
	 * Get files that need synced
	 */
	if (isset($cdn_settings->remove_local_files) && $cdn_settings->remove_local_files == true && count(get_local_files() > 0)) {
		$files_to_sync = array('upload' => array(), 'download' => array());
	} else {
		try {
			$files_to_sync = get_files_to_sync();
		} catch (Exception $exc) {
			$files_to_sync = array('upload' => array(), 'download' => array());
			$settings_error = true;
		}
	}
?>
<script type="text/javascript">
	var plugin_path = "<?php echo RS_CDN_URL ?>";
</script>
<div class="wrap rs_cdn">
	<h2 class="left">Rackspace CDN</h2>
	<div class="clear"></div>
	<hr />
	<form method="post" action="">
		<div id="error_notifications">
		<?php
			// Show error if error
			if (count($show_errors) > 0) {
				foreach ($show_errors as $cur_error) {
		?>
			<div id="setting-error-settings_updated" class="error settings-error"> 
				<p><strong>Ruh-Roh!</strong><br /><?php echo isset($cur_error) ? $cur_error : 'Your settings are busted. Please verify and make sure you have the correct credentials.' ?></p>
			</div>
		<?php
				}
			} else {
				// Show file sync notification
				if (isset($cdn_settings->remove_local_files) && $cdn_settings->remove_local_files == true) {
					// Remove local files after upload, do not sync
					$num_files_to_sync = count($files_to_sync['upload']);
					$show_file_count = ($num_files_to_sync == 1) ? 'is ('.$num_files_to_sync.') file' : 'are ('.$num_files_to_sync.') files';
					$show_needs = ($num_files_to_sync == 1) ? 'needs' : 'need';
				} else {
					// Sync files between CDN and local server (DO NOT REMOVE AFTER UPLOAD)
					$num_files_to_sync = count($files_to_sync['upload'])+count($files_to_sync['download']);
					$show_file_count = ($num_files_to_sync == 1) ? 'is ('.$num_files_to_sync.') file' : 'are ('.$num_files_to_sync.') files';
					$show_needs = ($num_files_to_sync == 1) ? 'needs' : 'need';
				}
		?>
            <div id="setting-error-settings_updated" class="updated settings-error"<?php echo ($num_files_to_sync == 0) ? ' style="display:none;"' : '' ?>> 
				<p><strong>Hey! You there!</strong><br />There <?php echo $show_file_count ?> that <?php echo $show_needs ?> synchronized with the CDN. Click the <em>Synchronize</em> button below to sync your files.</p>
			</div>
		<?php
			}

            // Make sure cache file is writable
            if (!is_writable(RS_CDN_PATH) || !is_writable(RS_CDN_PATH.'object_cache')) {
        ?>
            <div class="error"> 
				<p><strong>Uh-Oh!</strong><br />Looks like the "object_cache" file is not writable. Please fix this to improve load time performance.</p>
			</div>
        <?php
            }
		?>
		</div>
		<div id="upload_files_to_cdn"<?php echo ($settings_error == true || count($show_errors) > 0) ? ' style="display:none;"' : '' ?>>
			<h3>Moving Files To CDN</h3>
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<label>File Sync and Verification</label>
						</th>
						<td>
							<p id="all_files_in_sync">
								<?php if ($num_files_to_sync > 0) : ?>
								<a href="#" class="button" id="synchronize" data-blogurl="<?php echo site_url();?>">Synchronize</a>
								<?php else : ?>
								<em>All Files 'N Sync</em>
								<?php endif; ?>
								<br />
								<span id="file_upload" class="description"></span>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<h3<?php echo ($settings_error == true || count($show_errors) > 0) ? ' style="display:none;"' : '' ?>>Manage Files</h3>
		<table class="form-table"<?php echo ($settings_error == true || count($show_errors) > 0) ? ' style="display:none;"' : '' ?>>
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[use_ssl]">Remove Local Files</label>
					</th>
					<td>
						<input type="checkbox" name="rs_cdn[remove_local_files]" value="true" <?php echo (isset($cdn_settings->remove_local_files) && $cdn_settings->remove_local_files == true) ? 'checked': '' ?>> 
						<span class="description">Remove files from local server when uploaded to CDN?</span>
					</td>
				</tr>
				<?php if (isset($cdn_settings->remove_local_files) && $cdn_settings->remove_local_files == true) : ?>
				<tr id="remove_local_files_container" valign="top"<?php echo (count(get_local_files()) == 0) ? ' style="display:none;"' : ((count($files_to_sync['upload']) > 0) ? ' style="display:none;"' : '') ?>>
					<th scope="row"></th>
					<td>
						<a href="#" class="button" id="remove_local_files" style="color:#ff0000;vertical-align:middle;" data-blogurl="<?php echo site_url();?>">Remove Local Files</a>
						<span class="description" style="color:#ff0000;vertical-align:middle;"><strong>WARNING:</strong> Only click this button once you have confirmed all files have been synchronized to the CDN.</span>
						<br />
						<span id="file_delete" class="description"></span>
					</td>
				</tr>
				<?php endif; ?>
				<tr valign="top">
					<th scope="row">
						<label>Files To Ignore</label>
					</th>
					<td>
						<div class="description" style="padding-bottom:10px;font-style:italic;">Files extensions to ignore when uploading (Comma separated, no spaces).</div>
						<textarea name="rs_cdn[files_to_ignore]" style="width:350px;height:150px;"><?php echo (isset($cdn_settings->files_to_ignore)) ? $cdn_settings->files_to_ignore : ''; ?></textarea>
					</td>
				</tr>
			</tbody>
		</table>
		<br/>
	    <h3>Default Rackspace CDN Settings</h3>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[username]">Username</label>
					</th>
					<td>
						<input name="rs_cdn[username]" type="text" value="<?php echo $cdn_settings->username; ?>" class="regular-text" required="required" />
						<input name="rs_cdn_old[username]" type="hidden" value="<?php echo $cdn_settings->username; ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[apiKey]">API Key</label>
					</th>
					<td>
						<input name="rs_cdn[apiKey]" type="text" value="<?php echo $cdn_settings->apiKey;?>" class="regular-text" required="required" />
						<input name="rs_cdn_old[apiKey]" type="hidden" value="<?php echo $cdn_settings->apiKey; ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[container]">Container</label>
					</th>
					<td>
						<input name="rs_cdn[container]" type="text" value="<?php echo $cdn_settings->container;?>" class="regular-text" required="required" />
						<input name="rs_cdn_old[container]" type="hidden" value="<?php echo $cdn_settings->container; ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[custom_cname]">Custom CNAME</label>
					</th>
					<td>
						<input name="rs_cdn[custom_cname]" id="rs_cdn_custom_cname" type="text" value="<?php echo (isset($cdn_settings->custom_cname)) ? $cdn_settings->custom_cname : '';?>" class="regular-text" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[use_ssl]">File Paths</label>
					</th>
					<td>
						<input type="checkbox" name="rs_cdn[use_ssl]" id="rs_cdn_use_ssl" value="true" <?php echo (isset($cdn_settings->use_ssl) && $cdn_settings->use_ssl == true) ? 'checked': '' ?><?php echo (isset($cdn_settings->custom_cname) && trim($cdn_settings->custom_cname) != '') ? ' disabled' : '' ?>> 
						<span class="description">Use SSL (https) file paths?</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[region]">Region</label>
					</th>
					<td>
						<select name="rs_cdn[region]" required="required">
							<?php
								$cdn_regions = array("IAD"=>"Northern Virginia", "ORD" => "Chicago", "DFW" => "Dallas", "LON" => "London", "HKG" => "Hong Kong", "SYD" => "Sydney");
								foreach ($cdn_regions as $code => $region) {
									$selected = ($cdn_settings->region ==  $code) ? ' selected' : '';
									echo '<option value="'.$code.'"'.$selected.'>'.$region.' ('.$code.')</option>';
								}
							?>
						</select>
						<input name="rs_cdn_old[region]" type="hidden" value="<?php echo $cdn_settings->region; ?>" />
						<br /><span class="description">Rackspace filestore region.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[url]">API Version URL</label>
					</th>
					<td>
						<input name="rs_cdn[url]" type="text" value="<?php echo $cdn_settings->url;?>" class="regular-text" required="required" readonly="readonly" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"></th>
					<td>
						<p><input class="button-primary" class="left" type="submit" name="save_cdn_settings" value="Save" />&nbsp;</p>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
</div>