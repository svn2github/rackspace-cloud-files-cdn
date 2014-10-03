/**
 * Define variables used
 */
var rs_cdn_queue = null;
var rs_cdn_delete_queue = null;
var rs_cdn_queue_length = 0;
var rs_cdn_delete_queue_length = 0;


/**
 * Setup sync button on page load
 */
var $rs_cdn = jQuery.noConflict();
$rs_cdn(document).ready(function() {
	// Sync button
	$rs_cdn('#synchronize').click(function(e) {
		e.preventDefault();

		$rs_cdn('#file_upload').html('<br/><strong>Starting sync, this may take a bit...</strong>');

		$rs_cdn(this).attr('disabled','disabled');
		$rs_cdn(this).html('Syncing Files...');

		$rs_cdn.ajax({
			url: ajaxurl,
			data: { action: 'get_files' }
		}).done(function(resp) {
			if (resp == "error") {
				$rs_cdn('#file_upload').html("<span style=\"color:#ff0000;\">There was an error processing your request:<br/><br/>"+resp+"</span>");
			} else {
				// Try to parse response JSON
				var response = null;
				try {
					response = $rs_cdn.parseJSON(resp);
				} catch (err) {
					response = null;
				}

                // Number of files to sync
                var rs_cdn_num_files_to_sync = response.length;

				// Parse response OR error out
				if (response != null) {
					// Get first file name
					var first_file;
					for(var key in response) {
					    if(response.hasOwnProperty(key)) {
					        first_file = response[key].fn;
					        break;
					    }
					}
					var first_file_name = first_file.substring(first_file.lastIndexOf('/')+1);

					// Let the user know we're starting sync
					$rs_cdn('#file_upload').html('Syncing '+first_file_name+'...');

					// Add each file to AJAX queue for sync
					rs_cdn_queue = new $rs_cdn.AjaxQueue();
					$rs_cdn.each(response, function(key, value) {
						var file_name = value.fn.substring(value.fn.lastIndexOf('/')+1);
						var file_path = encodeURIComponent(value.fn);
						rs_cdn_queue_length++;
						var current_file_num = rs_cdn_queue_length;
						rs_cdn_queue.add({
							url: ajaxurl+'?action=sync_existing_file&file_path='+file_path+'&current_file_num='+current_file_num+'&rs_cdn_num_files_to_sync='+rs_cdn_num_files_to_sync,
							success: function(resp) {
								// Try to parse response JSON
								var response = null;
								try {
									response = $rs_cdn.parseJSON(resp);
								} catch (err) {
									response = null;
								}

								// If response is successful
								if ( response != null && response.response != null && response.response != '' && response.response != 'error' ) {
									$rs_cdn('#file_upload').html('');
									rs_cdn_queue_length--;
									$rs_cdn('#setting-error-settings_updated').html($rs_cdn('#setting-error-settings_updated').html().replace(/ *\([^)]*\) */g, " ("+rs_cdn_queue_length+") "));
									if (rs_cdn_queue_length == 0) {
										$rs_cdn('#setting-error-settings_updated').hide();
										$rs_cdn('#all_files_in_sync').html('<em>All Files \'N Sync</em>');
										$rs_cdn('#remove_local_files_container').show();
									}
								} else {
									$rs_cdn('#file_upload').html('');
									if (response != null && response.message != null && response.message != '') {
										$rs_cdn('#setting-error-settings_updated').after('<div id="'+key+'" class="error settings-error"><p><input class="button-primary" type="button" style="font-size:12px;padding:0px 8px;height:28px;" value="Retry" onclick="retry_upload(\''+key+'\', \''+file_path+'\', \''+file_name+'\')"> '+response.message+'</p></div>');
									} else {
										$rs_cdn('#setting-error-settings_updated').after('<div id="'+key+'" class="error settings-error"><p><input class="button-primary" type="button" style="font-size:12px;padding:0px 8px;height:28px;" value="Retry" onclick="retry_upload(\''+key+'\', \''+file_path+'\', \''+file_name+'\')"> Sync for "'+file_name+'" failed.</p></div>');
									}
								}
							},
							_run: function(req) {
								$rs_cdn('#file_upload').html('<br/><em style="font-weight:bold;">Syncing '+file_name+'...</em>');
							}
						});
					});
				} else {
					$rs_cdn('#file_upload').html("<span style=\"color:#ff0000;\">There was an error processing your request:<br/><br/>"+JSON.stringify(error)+"</span>");
				}
			}
		}).fail(function(error) {
			// Let the user know the request failed
			$rs_cdn('#file_upload').html("<span style=\"color:#ff0000;\">There was an error processing your request:<br/><br/>"+JSON.stringify(error)+"</span>");
		});

		// Re-enablel the button
		$rs_cdn(this).removeAttr('disabled');
		$rs_cdn(this).html('Synchronize');
	});

	// Remove local files button
	$rs_cdn('#remove_local_files').click(function(e) {
		e.preventDefault();

		$rs_cdn('#file_delete').html('<br/><strong>Starting removal...</strong>');

		$rs_cdn(this).attr('disabled','disabled');
		$rs_cdn(this).html('Removing Files...');

		$rs_cdn.ajax({
			url: ajaxurl,
			data: { action: 'get_files_to_remove' }
		}).done(function(resp) {
			if (resp == "error") {
				$rs_cdn('#file_delete').html("<span style=\"color:#ff0000;\">There was an error processing your request:<br/><br/>"+resp+"</span>");
			} else {
				// Try to parse response JSON
				var response = null;
				try {
					response = $rs_cdn.parseJSON(resp);
				} catch (err) {
					response = null;
				}

                // Number of files to sync
                var rs_cdn_num_files_to_delete = response.length;

				// Parse response OR error out
				if (response != null) {
					// Get first file name
					var first_file;
					for(var key in response) {
					    if(response.hasOwnProperty(key)) {
					        first_file = response[key].fn;
					        break;
					    }
					}
					var first_file_name = first_file.substring(first_file.lastIndexOf('/')+1);

					// Let the user know we're starting removal
					$rs_cdn('#file_delete').html('Removing '+first_file_name+'...');

					// Add each file to AJAX queue for removal
					rs_cdn_delete_queue = new $rs_cdn.AjaxQueue();
					$rs_cdn.each(response, function(key, value) {
						var file_name = value.fn.substring(value.fn.lastIndexOf('/')+1);
						var file_path = encodeURIComponent(value.fn);
						rs_cdn_delete_queue_length++;
						var current_file_num = rs_cdn_delete_queue;
						rs_cdn_delete_queue.add({
							url: ajaxurl+'?action=remove_existing_file&file_path='+file_path+'&current_file_num='+current_file_num+'&rs_cdn_num_files_to_sync='+rs_cdn_num_files_to_delete,
							success: function(resp) {
								// Try to parse response JSON
								var response = null;
								try {
									response = $rs_cdn.parseJSON(resp);
								} catch (err) {
									response = null;
								}

								// If response is successful
								if ( response != null && response.response != 'error' ) {
									$rs_cdn('#file_delete').html('');
									rs_cdn_delete_queue_length--;
									$rs_cdn('#setting-error-settings_updated').html($rs_cdn('#setting-error-settings_updated').html().replace(/ *\([^)]*\) */g, " ("+rs_cdn_delete_queue_length+") "));
									if (rs_cdn_delete_queue_length == 0) {
										$rs_cdn('#setting-error-settings_updated').hide();
										$rs_cdn('#all_files_in_sync').html('<em>All Files \'N Sync</em>');
										$rs_cdn('#remove_local_files_container').hide();
									}
								} else {
									$rs_cdn('#file_delete').html('');
									if (response != null && response.message != null && response.message != '') {
										$rs_cdn('#setting-error-settings_updated').after('<div id="'+key+'" class="error settings-error"><p><input class="button-primary" type="button" style="font-size:12px;padding:0px 8px;height:28px;" value="Retry" onclick="retry_remove(\''+key+'\', \''+file_path+'\', \''+file_name+'\')"> '+response.message+'</p></div>');
									} else {
										$rs_cdn('#setting-error-settings_updated').after('<div id="'+key+'" class="error settings-error"><p><input class="button-primary" type="button" style="font-size:12px;padding:0px 8px;height:28px;" value="Retry" onclick="retry_remove(\''+key+'\', \''+file_path+'\', \''+file_name+'\')"> Removal of "'+file_name+'" failed.</p></div>');
									}
								}
							},
							_run: function(req) {
								$rs_cdn('#file_delete').html('<br/><em style="font-weight:bold;">Removing '+file_name+'...</em>');
							}
						});
					});
				} else {
					$rs_cdn('#file_delete').html("<span style=\"color:#ff0000;\">There was an error processing your request:<br/><br/>"+JSON.stringify(error)+"</span>");
				}
			}
		}).fail(function(error) {
			// Let the user know the request failed
			$rs_cdn('#file_delete').html("<span style=\"color:#ff0000;\">There was an error processing your request:<br/><br/>"+JSON.stringify(error)+"</span>");
		});

		// Re-enablel the button
		$rs_cdn(this).removeAttr('disabled');
		$rs_cdn(this).html('Remove Local Files');
	});

	// Check if custon CNAME is blank or not, set SSL accordingly
	var rs_cdn_has_custom_cname = $rs_cdn('#rs_cdn_use_ssl').attr('checked');
	$rs_cdn('#rs_cdn_custom_cname').keyup(function() {
		if ($rs_cdn(this).val().length > 0) {
			$rs_cdn('#rs_cdn_use_ssl').attr('disabled','disabled');
			$rs_cdn('#rs_cdn_use_ssl').removeAttr('checked');
		} else {
			$rs_cdn('#rs_cdn_use_ssl').removeAttr('disabled');
			if (rs_cdn_has_custom_cname) {
				$rs_cdn('#rs_cdn_use_ssl').attr('checked','checked');
			}
		}
	});
});


/**
 * Retry uploading attachment
 */
function retry_upload(file_id, file_path, file_name) {
	$rs_cdn('#'+file_id).html('<p><input class="button-primary" type="button" style="font-size:12px;padding:0px 8px;height:28px;" value="Retrying..." onclick="retry_upload(\''+file_id+'\', \''+file_path+'\', \''+file_name+'\')"> '+file_name+'</p>');
	rs_cdn_queue.add({
		url: ajaxurl+'?action=sync_existing_file&file_path='+file_path+'&retry_upload=true',
		success: function(resp) {
			// Try to parse response JSON
			var response = null;
			try {
				response = $rs_cdn.parseJSON(resp);
			} catch (err) {
				response = null;
			}

			// If response is successful
			if ( response != null && response.response != 'error' ) {
				rs_cdn_queue_length--;
				$rs_cdn('#setting-error-settings_updated').html($rs_cdn('#setting-error-settings_updated').html().replace(/ *\([^)]*\) */g, " ("+rs_cdn_queue_length+") "));
				$rs_cdn('#'+file_id).remove();
				if (rs_cdn_queue_length == 0) {
					$rs_cdn('#setting-error-settings_updated').hide();
					$rs_cdn('#all_files_in_sync').html('<em>All Files \'N Sync</em>');
					$rs_cdn('#remove_local_files_container').show();
				}
			} else {
				if ($rs_cdn('#'+file_id).length > 0) {
					$rs_cdn('#'+file_id).html('<p><input class="button-primary" type="button" style="font-size:12px;padding:0px 8px;height:28px;" value="Retry" onclick="retry_upload(\''+file_id+'\', \''+file_path+'\', \''+file_name+'\')"> Sync for "'+file_name+'" failed.</p>');
				} else {
					$rs_cdn('#setting-error-settings_updated').after('<div id="'+file_id+'" class="error settings-error"><p><input class="button-primary" type="button" style="font-size:12px;padding:0px 8px;height:28px;" value="Retry" onclick="retry_upload(\''+file_id+'\', \''+file_path+'\', \''+file_name+'\')"> Sync for "'+file_name+'" failed.</p></div>');
				}
			}
		}
	});
}


/**
 * Retry uploading attachment
 */
function retry_remove(file_id, file_path, file_name) {
	$rs_cdn('#'+file_id).html('<p><input class="button-primary" type="button" style="font-size:12px;padding:0px 8px;height:28px;" value="Retrying..." onclick="retry_remove(\''+file_id+'\', \''+file_path+'\', \''+file_name+'\')"> '+file_name+'</p>');
	rs_cdn_delete_queue.add({
		url: ajaxurl+'?action=remove_existing_file&file_path='+file_path,
		success: function(resp) {
			// Try to parse response JSON
			var response = null;
			try {
				response = $rs_cdn.parseJSON(resp);
			} catch (err) {
				response = null;
			}

			// If response is successful
			if ( response != null && response.response != 'error' ) {
				rs_cdn_delete_queue_length--;
				$rs_cdn('#setting-error-settings_updated').html($rs_cdn('#setting-error-settings_updated').html().replace(/ *\([^)]*\) */g, " ("+rs_cdn_delete_queue_length+") "));
				$rs_cdn('#'+file_id).remove();
				if (rs_cdn_delete_queue_length == 0) {
					$rs_cdn('#setting-error-settings_updated').hide();
					$rs_cdn('#all_files_in_sync').html('<em>All Files \'N Sync</em>');
					$rs_cdn('#remove_local_files_container').hide();
				}
			} else {
				if ($rs_cdn('#'+file_id).length > 0) {
					$rs_cdn('#'+file_id).html('<p><input class="button-primary" type="button" style="font-size:12px;padding:0px 8px;height:28px;" value="Retry" onclick="retry_remove(\''+file_id+'\', \''+file_path+'\', \''+file_name+'\')"> Removal of "'+file_name+'" failed.</p>');
				} else {
					$rs_cdn('#setting-error-settings_updated').after('<div id="'+file_id+'" class="error settings-error"><p><input class="button-primary" type="button" style="font-size:12px;padding:0px 8px;height:28px;" value="Retry" onclick="retry_remove(\''+file_id+'\', \''+file_path+'\', \''+file_name+'\')"> Removal of "'+file_name+'" failed.</p></div>');
				}
			}
		}
	});
}


/**
 * AJAX queue used to synchronously upload files
 */
$rs_cdn.AjaxQueue = function() {
	this.reqs = [];
	this.requesting = false;
};
$rs_cdn.AjaxQueue.prototype = {
	add: function(req) {
		this.reqs.push(req);
		this.next();
	},
	next: function() {
		if (this.reqs.length == 0)
			return;
		if (this.requesting == true)
			return;
		var req = this.reqs.splice(0, 1)[0];
		var complete = req.complete;
		var self = this;
		if (req._run)
			req._run(req);
		req.complete = function() {
			if (complete)
				complete.apply(this, arguments);
			self.requesting = false;
				self.next();
		}
		this.requesting = true;
		$rs_cdn.ajax(req);
	}
};