=== Rackspace CDN ===
Contributors: bstump
Tags: cdn, rackspace, cloud files
Requires at least: 3.8.1
Tested up to: 3.9.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Stores all of your WordPress media on Rackspace's Cloud Files CDN. Once the file is uploaded, it is deleted from the local server.

== Description ==

Allows any media in your WordPress uploads folder to be uploaded to Rackspace's Cloud Files CDN. ** If you are doing an initial upload, backup your uploads directory first, just in case. I’ve not had any issues, but wanted to include this disclaimer.

= Version 1.0 =

Added latest version of php-opencloud (1.9.2). Changes to speed up plugin. Content Type now reported.

== Installation ==

1. Upload plugin to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Backup uploads directory (optional, if doing initial upload)
4. Use it! Save money!

== Screenshots ==

1. Admin page when all files are synced.

2. Admin page when there files that need synced.

3. Admin page while syncing files in real-time.

== Changelog ==

= 0.0.1 =
* Initial version.

= 0.0.2 =
* General bug fixes
* Added custom CNAME option.

= 0.0.7 =
* General bug fixes
* Removed code that was no longer needed
* Fixed issue with adding media to old pages breaking URLs

= 1.0 =
* General bug fixes
* Other changes to improve and speed up functionality, this version is much quicker
* Added latest version of php-opencloud (if PHP 5.3.3 or greater)
* Added content types to old version of php-opencloud, new version supports it as well

== Upgrade Notice ==

= 1.0 =
* General bug fixes
* Other changes to improve and speed up functionality, this version is much quicker
* Added latest version of php-opencloud (if PHP 5.3.3 or greater)
* Added content types to old version of php-opencloud, new version supports it as well