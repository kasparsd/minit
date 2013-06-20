=== Minit ===
Contributors: kasparsd
Tags: css, js, combine, minify, concatenate, optimization, performance, speed
Requires at least: 3.1
Tested up to: 3.6
Stable tag: trunk
License: GPLv2 or later

Combine CSS files and Javascript files into single file in the correct order. Use the latest modified time in filename generation to ensure freshness. Load all external Javascript files asynchronosly.


== Description ==

TODO


== Screenshots ==

1. All CSS files combined in a single file
2. All external Javascript files loading asynchronosly


== Changelog ==

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