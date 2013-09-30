=== Minit ===
Contributors: kasparsd
Tags: css, js, combine, minify, concatenate, optimization, performance, speed
Requires at least: 3.1
Tested up to: 3.7
Stable tag: trunk
License: GPLv2 or later

Combine CSS files and Javascript files into single file in the correct order. Use the latest modified time in filename generation to ensure freshness. Load all external Javascript files asynchronosly.


== Description ==

TODO


== Screenshots ==

1. All CSS files combined in a single file
2. All external Javascript files loading asynchronosly


== Changelog ==

= 0.8.4 =
* Include is_ssl() in cache key

= 0.8.2 =
* Don't use `set_url_scheme()` because it assumes SSL by default

= 0.8.1 =
* Use `set_url_scheme()` instead of an `is_ssl()` check

= 0.8 =
* Add SSL support

= 0.6.8 =
* Add a filter to allow other plugins interact with combined file content.

= 0.6.7 =
* Add "Clear cache" link in the plugin list.

= 0.6.6 =
* Store cache file path in transient so that we can verify it exists before using it.

= 0.6.5 =
* Make sure that cache file exists before using it.

= 0.6.4 =
* Set cache lifetime to 24h and use file versions as cache key.

= 0.6.3 =
* Mark merged items as done.
* Use cache key for script handle key instead of minit-css and minit-js.

= 0.6.2 =
* Add a caching layer.

= 0.6.1 =
* Print related inline scripts, if any.
* Add remaining scripts to the print queue.

= 0.6 =
* Almost a complete rewrite to merge the functionality of minit logic.
* Fixes CSS URL path for scripts that use relative URLs.

= 0.5.1 =
* Check if the WP_Scripts object exists before doing anything.

= 0.5 =
* Fix JS file dependency resolving.

= 0.4 =
* Initial public release.