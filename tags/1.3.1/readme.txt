=== Rackspace CDN ===
Contributors: bstump
Tags: cdn, rackspace, cloud files, sync, files
Requires at least: 3.8.1
Tested up to: 4.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Syncs and serves all of your WordPress media via Rackspace's Cloud Files CDN.

== Description ==

Syncs and serves all of your WordPress media via Rackspace's Cloud Files CDN. You can sync the media and keep it on your local server (using disk space on both your local server and the CDN) or you can opt to remove media from your local server once it has been synced to the CDN (saving you disk space and bandwidth at your hosting provider, reducing your costs).

= Version 1.3.1 =

Service affecting bug fix.

== Installation ==

1. Upload plugin to '/wp-content/plugins/' OR install from WP plugin directory
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

= 1.1.2 =
* General bug fixes

= 1.1.3 =
* General bug fixes
* Upgraded OpenCloud to v1.10.1

= 1.1.4 =
* General bug fixes

= 1.1.5 =
* General bug fixes
* CDN object caching to speed up response time

= 1.2.0 =
* Switched back to older php-opencloud version to resolve upload issues
* Implemented custom MIME content types for file uploads

= 1.3.0 =
* Fixed issue with some images in blog posts not being rewritten to load from CDN
* Fixed issues with loading speed
* Changed from storing cache in session variable to storing it in a cache file
* Other general bug fixes

= 1.3.1 =
* Service affecting bug fix.

== Upgrade Notice ==

= 1.3.1 =
* Service affecting bug fix.