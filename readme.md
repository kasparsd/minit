# Minit

Contributors: kasparsd   
Tags: css, js, combine, minify, concatenate, optimization, performance, speed   
Requires at least: 3.1   
Tested up to: 4.4   
Stable tag: trunk   
License: GPLv2 or later   

Combine CSS files and Javascript files into single file in the correct order. Use the latest modified time in filename generation to ensure freshness. Load all external Javascript files asynchronously.


## Description

TODO


## Screenshots

1. All CSS files combined in a single file
2. All external Javascript files loading asynchronously


## Changelog

### 1.3.0 (August 28, 2016)
* Refactor the code for readability.
* Fix issue with processing files already processed by Minit.

### 1.2
* Add Minit JS using async.

### 1.1
* Add support for multiple inline CSS data.

### 1.0
* Refactor Minit logic. Fix double Minit with JS in the footer.

### 0.9
* Refactor into a class.
* Deal with plugins calling `wp_print_scripts()` directly. Looking at you, Akismet.

### 0.8.7
* Use a global version number instead of deleting files for cache purge.
* Add filters `minit-exclude-css` and `minit-exclude-css` to exclude files from Minit.
* Add action `minit-cache-purge-delete` that deletes all Minit files. Can be called from cron, for example.
* Call action `minit-cache-purged` when the global cache version number is bumped.

### 0.8.4
* Include is_ssl() in cache key

### 0.8.2
* Don't use `set_url_scheme()` because it assumes SSL by default

### 0.8.1
* Use `set_url_scheme()` instead of an `is_ssl()` check

### 0.8
* Add SSL support

### 0.6.8
* Add a filter to allow other plugins interact with combined file content.

### 0.6.7
* Add "Clear cache" link in the plugin list.

### 0.6.6
* Store cache file path in transient so that we can verify it exists before using it.

### 0.6.5
* Make sure that cache file exists before using it.

### 0.6.4
* Set cache lifetime to 24h and use file versions as cache key.

### 0.6.3
* Mark merged items as done.
* Use cache key for script handle key instead of minit-css and minit-js.

### 0.6.2
* Add a caching layer.

### 0.6.1
* Print related inline scripts, if any.
* Add remaining scripts to the print queue.

### 0.6
* Almost a complete rewrite to merge the functionality of minit logic.
* Fixes CSS URL path for scripts that use relative URLs.

### 0.5.1
* Check if the WP_Scripts object exists before doing anything.

### 0.5
* Fix JS file dependency resolving.

### 0.4
* Initial public release.
