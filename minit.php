<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Description: Combine JS and CSS files and serve them from the uploads folder.
Version: 1.4.0
Author: Kaspars Dambis
Author URI: https://kaspars.net
*/

// Until we add proper autoloading.
include dirname( __FILE__ ) . '/src/minit-assets.php';
include dirname( __FILE__ ) . '/src/minit-asset-cache.php';
include dirname( __FILE__ ) . '/src/minit-js.php';
include dirname( __FILE__ ) . '/src/minit-css.php';
include dirname( __FILE__ ) . '/src/minit-plugin.php';
include dirname( __FILE__ ) . '/src/admin.php';
include dirname( __FILE__ ) . '/src/helpers.php';

add_action( 'plugins_loaded', array( 'Minit_Plugin', 'instance' ) );
