<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Description: Combine JS and CSS files and serve them from the uploads folder.
Version: 1.3-dev
Author: Kaspars Dambis
Author URI: http://kaspars.net
*/

// Core Minit functionality
include dirname( __FILE__ ) . '/include/minit.php';

// Include the admin functionality
include dirname( __FILE__ ) . '/include/helpers.php';

// Include the admin functionality
include dirname( __FILE__ ) . '/include/admin.php';


Minit_Plugin::instance();

class Minit_Plugin {

  public $version = 'minit-1.3';
  public $minit;
  public $plugin_admin;
  public $plugin_file;


  public static function instance() {

    static $instance;

    if ( ! $instance )
      $instance = new self();

    return $instance;

  }


  protected function __construct() {

    $this->plugin_file = __FILE__;

    add_action( 'init', array( $this, 'init' ) );

  }


  public function init() {

    $this->minit = new Minit( $this );
    $this->plugin_admin = new Minit_Admin( $this );

    if ( ! is_admin() )
      $this->minit->hook();

    // This action can used to delete all Minit cache files from cron
		add_action( 'minit-cache-purge-delete', array( $this->minit, 'cache_delete' ) );

  }


}
