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

add_action( 'plugins_loaded', array( 'Minit_Plugin', 'instance' ) );

class Minit_Plugin {

  public $version = 'minit-1.3';
  public $minit;
  public $admin;
  public $plugin_file;


  public static function instance() {

    static $instance;

    if ( ! $instance )
      $instance = new self();

    return $instance;

  }


  protected function __construct() {

    include dirname( __FILE__ ) . '/include/minit.php';
    include dirname( __FILE__ ) . '/include/helpers.php';
    include dirname( __FILE__ ) . '/include/admin.php';

    $this->plugin_file = __FILE__;

    $this->minit = new Minit( $this );
    $this->admin = new Minit_Admin( $this );

    $this->init();

  }


  public function init() {

    if ( ! is_admin() )
      $this->minit->init();

    $this->admin->init();

    // This action can used to delete all Minit cache files from cron
		add_action( 'minit-cache-purge-delete', array( $this->minit, 'cache_delete' ) );

  }


}
