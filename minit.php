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

    include dirname( __FILE__ ) . '/include/minit-assets.php';
    include dirname( __FILE__ ) . '/include/minit-js.php';
    include dirname( __FILE__ ) . '/include/minit-css.php';
    include dirname( __FILE__ ) . '/include/helpers.php';
    include dirname( __FILE__ ) . '/include/admin.php';

    $minit_js = new Minit_Js( $this );
    $minit_css = new Minit_Css( $this );
    $minit_admin = new Minit_Admin( $this );

    if ( is_admin() ) {
      $minit_admin->init();
    } else {
      $minit_js->init();
      $minit_css->init();
    }

    // This action can used to delete all Minit cache files from cron
		add_action( 'minit-cache-purge-delete', array( $minit_js, 'cache_delete' ) );

  }


}
