<?php

class Minit_Admin {

	protected $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function init() {
		// Add a Purge Cache link to the plugin list
		// @todo Enable this for multisite somehow
		add_filter( 'plugin_action_links_' . $this->plugin->basename(), array( $this, 'plugin_action_link_cache_bump' ) );

		// Maybe purge minit cache
		add_action( 'admin_init', array( $this, 'maybe_purge_cache' ) );
	}

	/**
	 * Add a plugin link to purge the cache.
	 *
	 * @param  array $links List of plugin links.
	 *
	 * @return array
	 */
	public function plugin_action_link_cache_bump( $links ) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			wp_nonce_url( add_query_arg( 'purge_minit', true ), 'purge_minit' ),
			__( 'Purge Cache', 'minit' )
		);

		return $links;
	}

	/**
	 * Bump the Minit cache version.
	 *
	 * @return void
	 */
	public function maybe_purge_cache() {
		if ( isset( $_GET['purge_minit'] ) && check_admin_referer( 'purge_minit' ) ) {
			$this->plugin->cache_bump();

			add_action( 'admin_notices', array( $this, 'cache_purge_notice' ) );
		}
	}

	/**
	 * Show a notice that cache was purged.
	 *
	 * @return void
	 */
	public function cache_purge_notice() {
		printf(
			'<div class="updated"><p>%s</p></div>',
			__( 'Success: Minit cache purged.', 'minit' )
		);
	}

}
