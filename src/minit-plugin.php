<?php

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
	 * @var Minit_Asset_Cache
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
		// TODO: Pass this in when we move away from a singleton.
		$this->plugin_file = sprintf(
			'%s/minit.php',
			dirname( dirname( __FILE__ ) )
		);

		$this->minit_cache = new Minit_Asset_Cache( self::CACHE_DIR, self::CACHE_VERSION_KEY );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'admin_init' ) );

		// This action can used to bump and purge Minit cache from cron.
		add_action( 'minit-cache-purge-delete', array( $this, 'cache_purge' ) );
		add_action( 'minit-cache-version-bump', array( $this, 'cache_bump' ) );
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
