<?php

abstract class Minit_Assets {

	public $queue = array();
	public $done = array();
	public $handler;
	public $extension;


	function __construct( $handler, $extension = null ) {

		$this->handler = $handler;

		if ( ! empty( $extension ) )
			$this->extension = $extension;
		else
			$this->extension = get_class( $handler );

	}


	abstract function init();


	function register( $todo ) {

		if ( empty( $todo ) )
			return $todo;

		// Allow files to be excluded from Minit
		$minit_exclude = apply_filters( 'minit-exclude-' . $this->extension, array() );

		if ( ! is_array( $minit_exclude ) )
			$minit_exclude = array();

		$minit_todo = array_diff( $todo, $minit_exclude );

		if ( empty( $minit_todo ) )
			return $todo;

		foreach ( $minit_todo as $handle )
			if ( ! in_array( $handle, $this->queue ) )
				$this->queue[] = $handle;

		// Mark these as done since we'll take care of them
		$this->handler->done = array_merge( $this->handler->done, $this->queue );

		return $todo;

	}


	function minit() {

		$done = array();

		if ( empty( $this->queue ) )
			return false;

		// Build a cache key
		$ver = array(
			'is_ssl-' . is_ssl(), // Use different cache key for SSL and non-SSL
			'minit_cache_ver-' . get_option( 'minit_cache_ver' ), // Use a global cache version key to purge cache
		);

		// Include individual scripts versions in the cache key
		foreach ( $this->queue as $handle )
			$ver[] = sprintf( '%s-%s', $handle, $this->handler->registered[ $handle ]->ver );

		$cache_ver = md5( 'minit-' . implode( '-', $ver ) );

		// Try to get queue from cache
		//$cache = get_transient( 'minit-' . $cache_ver );

		if ( ! empty( $cache ) && isset( $cache['url'] ) ) {
			$this->mark_done( $cache['done'] );

			return $cache['url'];
		}

		foreach ( $this->queue as $handle ) {

			// Ignore pseudo packages such as jquery which return src as empty string
			if ( empty( $this->handler->registered[ $handle ]->src ) )
				$done[ $handle ] = null;

			// Get the relative URL of the asset
			$src = $this->get_asset_relative_path( $handle );

			// Skip if the file is not hosted locally
			if ( empty( $src ) || ! file_exists( ABSPATH . $src ) )
				continue;

			$item = $this->minit_item( file_get_contents( ABSPATH . $src ), $handle, $src );

			$item = apply_filters(
				'minit-item-' . $this->extension,
				$item,
				$this->handler,
				$handle
			);

			if ( false !== $item )
				$done[ $handle ] = $item;

		}

		if ( empty( $done ) )
			return false;

		$this->mark_done( array_keys( $done ) );

		$wp_upload_dir = wp_upload_dir();

		// Try to create the folder for cache
		if ( ! is_dir( $wp_upload_dir['basedir'] . '/minit' ) )
			if ( ! mkdir( $wp_upload_dir['basedir'] . '/minit' ) )
				return false;

		$combined_file_path = sprintf( '%s/minit/%s.%s', $wp_upload_dir['basedir'], $cache_ver, $this->extension );
		$combined_file_url = sprintf( '%s/minit/%s.%s', $wp_upload_dir['baseurl'], $cache_ver, $this->extension );

		// Allow other plugins to do something with the resulting URL
		$combined_file_url = apply_filters( 'minit-url-' . $this->extension, $combined_file_url, $done );

		// Allow other plugins to minify and obfuscate
		$done_imploded = apply_filters( 'minit-content-' . $this->extension, implode( "\n\n", $done ), $done );

		// Store the combined file on the filesystem
		if ( ! file_exists( $combined_file_path ) )
			if ( ! file_put_contents( $combined_file_path, $done_imploded ) )
				return false;

		// Cache this set of scripts, by default for 24 hours
		$cache_ttl = apply_filters( 'minit-cache-expiration', 24 * 60 * 60 );
		$cache_ttl = apply_filters( 'minit-cache-expiration-' . $this->extension, $cache_ttl );

		$result = array(
			'done' => array_keys( $done ),
			'url' => $combined_file_url,
			'file' => $combined_file_path,
		);

		set_transient( 'minit-' . $cache_ver, $result, $cache_ttl );

		return $combined_file_url;

	}


	abstract function process( $todo );


	function minit_item( $source, $handle, $src ) {

		return $source;

	}


	protected function mark_done( $handles ) {

		// Remove processed items from the queue
		$this->queue = array_diff( $this->queue, $handles );

		// Mark them as processed by Minit
		$this->done = array_merge( $this->done, $handles );

	}


	protected function get_asset_relative_path( $handle ) {

		if ( ! isset( $this->handler->registered[ $handle ] ) )
			return false;

		$item_url = $this->handler->registered[ $handle ]->src;

		if ( empty( $item_url ) )
			return false;

		// Remove protocol reference from the local base URL
		$base_url = preg_replace( '/^(https?:)/i', '', $this->handler->base_url );

		// Check if this is a local asset which we can include
		$src_parts = explode( $base_url, $item_url );

		if ( empty( $src_parts ) )
			return false;

		// Get the trailing part of the local URL
		$maybe_relative = array_pop( $src_parts );

		if ( file_exists( ABSPATH . $maybe_relative ) )
			return $maybe_relative;

		return false;

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
