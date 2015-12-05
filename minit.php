<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Description: Combine JS and CSS files and serve them from the uploads folder.
Version: 1.2
Author: Kaspars Dambis
Author URI: http://kaspars.net
*/

// Core Minit functionality
include dirname( __FILE__ ) . '/include/minit.php';

// Include the admin functionality
include dirname( __FILE__ ) . '/include/helpers.php';

// Include the admin functionality
include dirname( __FILE__ ) . '/include/admin.php';

// Go!
Minit::instance();
