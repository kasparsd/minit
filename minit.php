<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Update URI: https://updates.wpelevator.com/wp-json/update-pilot/v1/plugins
Description: Combine JS and CSS files and serve them from the uploads folder.
Version: 2.1.0
Author: Kaspars Dambis
Author URI: https://kaspars.net
Require PHP: 7.4
*/

if ( ! function_exists( 'add_action' ) ) {
	return;
}

// Until we add proper autoloading.
require_once __DIR__ . '/src/minit-assets.php';
require_once __DIR__ . '/src/minit-asset-cache.php';
require_once __DIR__ . '/src/minit-js.php';
require_once __DIR__ . '/src/minit-css.php';
require_once __DIR__ . '/src/minit-plugin.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/minit-admin.php';

add_action( 'plugins_loaded', array( Minit_Plugin::class, 'instance' ) );
