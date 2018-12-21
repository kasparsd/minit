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
include dirname( __FILE__ ) . '/src/minit-js.php';
include dirname( __FILE__ ) . '/src/minit-css.php';
include dirname( __FILE__ ) . '/src/admin.php';
include dirname( __FILE__ ) . '/src/helpers.php';

add_action( 'plugins_loaded', array( 'Minit_Plugin', 'instance' ) );

class Minit_Plugin {

	public $revision = '20160828';
	public $plugin_file;

	/**
	 * Absolute path to the cache directory.
	 *
	 * @var string
	 */
	protected $cache_dir;

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

		// Define the cache directory.
		$wp_upload_dir = wp_upload_dir( null, false ); // Don't create the directory.
		$this->cache_dir = sprintf( '%s/minit', $wp_upload_dir['basedir'] );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'admin_init' ) );

		// This action can used to delete all Minit cache files from cron
		add_action( 'minit-cache-purge-delete', array( $this, 'cache_delete' ) );
	}


	public function init() {
		if ( is_admin() || is_customize_preview() ) {
			return;
		}

		$js = new Minit_Js( $this );
		$css = new Minit_Css( $this );

		$js->init();
		$css->init();
	}

	public function admin_init() {
		$admin = new Minit_Admin( $this );
		$admin->init();
	}

	/**
	 * Bump the cache version.
	 *
	 * @return void
	 */
	protected function cache_bump() {
		// Use this as a global cache version number
		update_option( 'minit_cache_ver', time() );

		// Allow other plugins to know that we purged
		do_action( 'minit-cache-purged' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Delete cache files and bump the cache version.
	 *
	 * @return void
	 */
	public function cache_delete() {
		$minit_files = $this->get_cached_files();

		foreach ( $minit_files as $minit_file ) {
			unlink( $minit_file );
		}

		$this->cache_bump();
	}

	/**
	 * Get the absolute path to the cache directory.
	 *
	 * @return string
	 */
	public function cache_dir() {
		return $this->cache_dir;
	}

	/**
	 * Get all files in the cache.
	 *
	 * @return array
	 */
	public function get_cached_files() {
		$files = glob( $this->cache_dir() . '/*', GLOB_NOSORT );

		if ( ! empty( $files ) && is_array( $files ) ) {
			return $files;
		}

		return array();
	}

}
