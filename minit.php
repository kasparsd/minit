<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Description: Combine JS and CSS files and serve them from the uploads folder.
Version: 1.3-beta
Author: Kaspars Dambis
Author URI: http://kaspars.net
*/

add_action( 'plugins_loaded', array( 'Minit_Plugin', 'instance' ) );

class Minit_Plugin {

  public $version = 'minit-1.3';
  public $plugin_file;
  public $js;
  public $css;
  public $admin;


  public static function instance() {

    static $instance;

    if ( ! $instance )
      $instance = new self();

    return $instance;

  }


  protected function __construct() {

    $this->plugin_file = __FILE__;

    include dirname( __FILE__ ) . '/include/minit-assets.php';
    include dirname( __FILE__ ) . '/include/minit-js.php';
    include dirname( __FILE__ ) . '/include/minit-css.php';
    include dirname( __FILE__ ) . '/include/helpers.php';
    include dirname( __FILE__ ) . '/include/admin.php';

    $this->js = new Minit_Js( $this );
    $this->css = new Minit_Css( $this );
    $this->admin = new Minit_Admin( $this );

    $this->init();

  }


  public function init() {

    if ( is_admin() ) {
      $this->admin->init();
    } else {
      $this->js->init();
      $this->css->init();
    }

    // This action can used to delete all Minit cache files from cron
		add_action( 'minit-cache-purge-delete', array( $this, 'cache_delete' ) );

  }


  public static function cache_bump() {

    // Use this as a global cache version number
    update_option( 'minit_cache_ver', time() );

    // Allow other plugins to know that we purged
    do_action( 'minit-cache-purged' );

  }


  public static function cache_delete() {

    $wp_upload_dir = wp_upload_dir();
    $minit_files = glob( $wp_upload_dir['basedir'] . '/minit/*' );

    if ( $minit_files ) {
      foreach ( $minit_files as $minit_file ) {
        unlink( $minit_file );
      }
    }

  }


}
