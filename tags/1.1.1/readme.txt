=== Rackspace CDN ===
Contributors: bstump
Tags: cdn, rackspace, cloud files, sync, files
Requires at least: 3.8.1
Tested up to: 3.9.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Syncs and serves all of your WordPress media via Rackspace's Cloud Files CDN.

== Description ==

Syncs and serves all of your WordPress media via Rackspace's Cloud Files CDN. You can sync the media and keep it on your local server (using disk space on both your local server and the CDN) or you can opt to remove media from your local server once it has been synced to the CDN (saving you disk space at your hosting provider, reducing your costs).

= Version 1.1.1 =

General bug fixes.

== Installation ==

1. Upload plugin to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use it! Save money!

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

= 1.0.1 =
* Added uninstall hook to remove options when uninstalled.

= 1.1.0 =
* General bug fixes
* Added much anticipated sync feature

= 1.1.1 =
* General bug fixes

== Upgrade Notice ==

= 1.1.1 =
* General bug fixes