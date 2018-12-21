<?php

class Minit_Admin {

	protected $plugin;


	public function __construct( $plugin ) {

		$this->plugin = $plugin;

	}


	public function init() {

		// Add a Purge Cache link to the plugin list
		// @todo Enable this for multisite somehow
		add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin->plugin_file ), array( $this, 'plugin_action_link_cache_bump' ) );

		// Maybe purge minit cache
		add_action( 'admin_init', array( $this, 'cache_bump' ) );

	}


	function plugin_action_link_cache_bump( $links ) {

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			wp_nonce_url( add_query_arg( 'purge_minit', true ), 'purge_minit' ),
			__( 'Purge cache', 'minit' )
		);

		return $links;

	}


	function cache_bump() {

		if ( ! isset( $_GET['purge_minit'] ) || ! check_admin_referer( 'purge_minit' ) ) {
			return;
		}

		$this->plugin->cache_bump();

		add_action( 'admin_notices', array( $this, 'cache_bump_notice' ) );

	}


	function cache_bump_notice() {

		printf(
			'<div class="updated"><p>%s</p></div>',
			__( 'Success: Minit cache purged.', 'minit' )
		);

	}


}
