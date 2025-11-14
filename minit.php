<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Update URI: https://updates.wpelevator.com/wp-json/update-pilot/v1/plugins
Description: Combine JS and CSS files and serve them from the uploads folder.
Version: 2.0.0
Author: Kaspars Dambis
Author URI: https://kaspars.net
*/

// Until we add proper autoloading.
include __DIR__ . '/src/minit-assets.php';
include __DIR__ . '/src/minit-asset-cache.php';
include __DIR__ . '/src/minit-js.php';
include __DIR__ . '/src/minit-css.php';
include __DIR__ . '/src/minit-plugin.php';
include __DIR__ . '/src/admin.php';
include __DIR__ . '/src/helpers.php';

if ( ! function_exists( 'add_action' ) ) {
	return;
}

add_action( 'plugins_loaded', array( Minit_Plugin::class, 'instance' ) );
