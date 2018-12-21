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
include dirname( __FILE__ ) . '/src/admin.php';
include dirname( __FILE__ ) . '/src/helpers.php';

add_action( 'plugins_loaded', array( 'Minit_Plugin', 'instance' ) );

class Minit_Plugin {

	/**
	 * Option key used for storing the cache version value.
	 *
	 * @var string
	 */
	const CACHE_VERSION_KEY = 'minit_cache_ver';

	/**
	 * Cache directory relative to the WP uploads directory.
	 *
	 * @var string
	 */
	const CACHE_DIR = 'minit';

	public $revision = '20160828';
	public $plugin_file;

	/**
	 * Instance of the Minit cache.
	 *
	 * @var Minit_Cache
	 */
	protected $minit_cache;

	/**
	 * Get the plugin instance.
	 *
	 * @return Minit_Plugin
	 */
	public static function instance() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Setup the class.
	 */
	protected function __construct() {
		$this->plugin_file = __FILE__;
		$this->minit_cache = new Minit_Asset_Cache( self::CACHE_DIR, self::CACHE_VERSION_KEY );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'admin_init' ) );

		// This action can used to delete all Minit cache files from cron.
		add_action( 'minit-cache-purge-delete', array( $this, 'cache_purge' ) );
	}

	/**
	 * Return the plugin basename.
	 *
	 * @return string
	 */
	public function basename() {
		return plugin_basename( $this->plugin_file );
	}

	/**
	 * Bump the cache version to bust the cache.
	 *
	 * @return boolean
	 */
	public function cache_bump() {
		return $this->minit_cache->bump();
	}

	/**
	 * Delete all the cache files.
	 *
	 * @return boolean
	 */
	public function cache_purge() {
		return $this->minit_cache->purge();
	}

	public function init() {
		// Don't do anything in the admin view.
		if ( is_admin() || is_customize_preview() ) {
			return;
		}

		$js = new Minit_Js( $this, $this->minit_cache );
		$css = new Minit_Css( $this, $this->minit_cache );

		$js->init();
		$css->init();
	}

	public function admin_init() {
		$admin = new Minit_Admin( $this );
		$admin->init();
	}

}
