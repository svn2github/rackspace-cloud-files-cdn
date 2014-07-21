<?php
	// Global CDN variable
	// global $rs_cdn;
	// $rs_cdn = (isset($_SESSION['cdn'])) ? $_SESSION['cdn'] : new RS_CDN();

	// Start session, if not started
	if( !session_id() )
		session_start();

	// Define session data
	defined('RS_CDN_PATH') or die();
	$_SESSION['cdn'] = (isset($_SESSION['cdn'])) ? $_SESSION['cdn'] : new RS_CDN();
	$_SESSION['cdn_settings'] = $_SESSION['cdn']->api_settings;

	// Save CDN settings
	save_cdn_settings();
	$settings_error = false;

	// Get files and counts
	$local_files = load_files_needing_upload();
	$local_count = count($local_files);

	// Check if connection has been made by grabbing container
	try {
		$container = $_SESSION['cdn']->container_object();
	} catch (Exception $e) {
		$settings_error = true;
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
			if ((isset($local_files['response']) && $local_files['response'] == 'error') || $settings_error == true) {
				$settings_error = true;
		?>
			<div id="setting-error-settings_updated" class="error settings-error"> 
				<p><strong>Ruh-Roh!</strong><br />Your settings are busted. Please verify and make sure you have the correct credentials.</p>
			</div>
		<?php
			} else {
				// Show file upload notification
				$show_file_count = (count($local_files) == 1) ? 'is ('.$local_count.') file' : 'are ('.$local_count.') files';
				$show_needs = (count($local_files) == 1) ? 'needs' : 'need';
		?>
				<div id="setting-error-settings_updated" class="updated settings-error"<?php echo ($local_count == 0) ? ' style="display:none;"' : '' ?>> 
				<p><strong>Hey! You there!</strong><br />There <?php echo $show_file_count ?> in your local WordPress uploads directory that <?php echo $show_needs ?> synchronized to the CDN. Click the <em>Synchronize</em> button to upload them.</p>
			</div>
		<?php
			}
		?>
		</div>
		<div id="upload_files_to_cdn"<?php echo ($settings_error == true) ? ' style="display:none;"' : '' ?>>
			<h3>Moving Files To CDN</h3>
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<label>File Sync and Verification</label>
						</th>
						<td>
							<p>
								<?php if ($local_count > 0) : ?>
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
		<h3<?php echo ($settings_error == true) ? ' style="display:none;"' : '' ?>>Manage Files</h3>
		<table class="form-table"<?php echo ($settings_error == true) ? ' style="display:none;"' : '' ?>>
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label>Files To Ignore</label>
					</th>
					<td>
						<div class="description" style="padding-bottom:10px;font-style:italic;">Files extensions to ignore when uploading (Comma separated, no spaces).</div>
						<textarea name="rs_cdn[files_to_ignore]" style="width:350px;height:150px;"><?php echo (isset($_SESSION['cdn_settings']['files_to_ignore'])) ? $_SESSION['cdn_settings']['files_to_ignore'] : ''; ?></textarea>
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
						<input name="rs_cdn[username]" type="text" value="<?php echo $_SESSION['cdn_settings']['username'];?>" class="regular-text" required="required" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[apiKey]">API Key</label>
					</th>
					<td>
						<input name="rs_cdn[apiKey]" type="text" value="<?php echo $_SESSION['cdn_settings']['apiKey'];?>" class="regular-text" required="required" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[container]">Container</label>
					</th>
					<td>
						<input name="rs_cdn[container]" type="text" value="<?php echo $_SESSION['cdn_settings']['container'];?>" class="regular-text" required="required" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[custom_cname]">Custom CNAME</label>
					</th>
					<td>
						<input name="rs_cdn[custom_cname]" type="text" value="<?php echo $_SESSION['cdn_settings']['custom_cname'];?>" class="regular-text" required="required" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[use_ssl]">Image Paths</label>
					</th>
					<td>
						<input type="checkbox" name="rs_cdn[use_ssl]" value="true" <?php echo (isset($_SESSION['cdn_settings']['use_ssl']) && $_SESSION['cdn_settings']['use_ssl'] == true) ? 'checked': '' ?>> 
						<span class="description">Use SSL (https) image paths?</span>
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
									$selected = ($_SESSION['cdn_settings']['region'] ==  $code) ? ' selected' : '';
									echo '<option value="'.$code.'"'.$selected.'>'.$region.' ('.$code.')</option>';
								}
							?>
						</select>
						<br /><span class="description">Rackspace filestore region.</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="rs_cdn[url]">API Version URL</label>
					</th>
					<td>
						<input name="rs_cdn[url]" type="text" value="<?php echo $_SESSION['cdn_settings']['url'];?>" class="regular-text" required="required" readonly="readonly" />
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