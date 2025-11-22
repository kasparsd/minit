<?php

abstract class Minit_Assets {

	public $queue = array();
	public $done = array();

	/**
	 * Instance of either WP scripts or styles.
	 *
	 * @var WP_Styles|WP_Scripts
	 */
	public $handler;

	public $extension;

	public $revision;

	public function __construct( $handler, $extension = null, $revision = null ) {
		$this->handler = $handler;

		if ( empty( $extension ) ) {
			$extension = get_class( $handler );
		}

		$this->extension = $extension;
		$this->revision = $revision;
	}

	abstract public function file_cache();

	abstract public function init();

	/**
	 * Register queued assets for Minit processing.
	 *
	 * @param  array $todo List of handles queued for the current request
	 *
	 * @return array       List of handles queued (unchanged)
	 */
	public function register( $todo ) {
		if ( empty( $todo ) ) {
			return $todo;
		}

		// Allow others to exclude handles from Minit.
		$excluded = $this->get_excluded( $todo );

		// Queue all of them for Minit.
		$this->queue = array_merge( $this->queue, array_diff( $todo, $excluded ) );

		// Return excluded handles back to the queue.
		return $excluded;
	}

	/**
	 * Get all dependencies of specified handles.
	 *
	 * @param string[] $handles All dependency handles.
	 *
	 * @return string[] List of dependency handles.
	 */
	protected function get_deps( array $handles ): array {
		$deps = array();

		foreach ( $handles as $handle ) {
			if ( isset( $this->handler->registered[ $handle ] ) ) {
				$deps = array_merge(
					$deps,
					$this->handler->registered[ $handle ]->deps
				);
			}
		}

		return array_unique( $deps );
	}

	/**
	 * Get the excluded handles and their dependencies.
	 *
	 * @param string[] $handles All handles to check.
	 *
	 * @return string[]
	 */
	protected function get_excluded( array $handles ): array {
		// Allow others to exclude handles from Minit.
		$excluded_handles = (array) apply_filters(
			'minit-exclude-' . $this->extension,
			array(),
			$handles
		);

		$excluded = array_intersect( $handles, $excluded_handles );

		if ( ! empty( $excluded ) ) {
			// Merge with dependencies of excluded handles.
			return array_merge( $excluded, $this->get_deps( $excluded ) );
		}

		return array();
	}

	/**
	 * Get a cache key for a set of assets for the current request.
	 *
	 * TODO: Account for before and after values being included
	 * in the bundle in case of CSS.
	 *
	 * @param  array  $handles List of asset handle strings.
	 *
	 * @return string
	 */
	protected function cache_key( $handles = array() ) {
		// Build a cache key.
		$ver = array(
			'revision-' . $this->revision,
			'is_ssl-' . is_ssl(), // Use different cache key for SSL and non-SSL.
			'minit_cache_ver-' . $this->file_cache()->version(), // Use a global cache version key to purge cache.
		);

		// Include individual scripts versions in the cache key.
		foreach ( $handles as $handle ) {
			$ver[] = sprintf( '%s-%s', $handle, $this->handler->registered[ $handle ]->ver );
		}

		return md5( 'minit-' . implode( '-', $ver ) );
	}

	/**
	 * The brains of Minit. Loops through all queued scripts and combines them into a single blob.
	 *
	 * @return string|boolean URL of the Minited file or `false` when queue empty or error.
	 */
	public function minit() {
		$done = array();

		if ( empty( $this->queue ) ) {
			return false;
		}

		$cache_ver = $this->cache_key( $this->queue );

		// Try to get queue from cache.
		$cache = $this->get_cache( $cache_ver );

		// Return right away, if we have a cached response.
		if ( ! empty( $cache ) && isset( $cache['url'] ) ) {
			$this->mark_done( $cache['done'] );

			return $cache['url'];
		}

		foreach ( $this->queue as $handle ) {
			// Get the relative URL of the asset.
			$src = $this->get_asset_relative_path( $handle );

			// Ignore pseudo packages such as jquery which return src as empty string.
			if ( ! empty( $src ) && is_readable( ABSPATH . $src ) ) {
				$item = $this->minit_item( file_get_contents( ABSPATH . $src ), $handle, $src );

				$done[ $handle ] = apply_filters(
					'minit-item-' . $this->extension,
					$item,
					$this->handler,
					$handle
				);
			}
		}

		if ( empty( $done ) ) {
			return false;
		}

		$this->mark_done( array_keys( $done ) );

		$cache_filename = sprintf( '%s.%s', $cache_ver, $this->extension );

		// Allow other plugins to minify and obfuscate.
		$done_imploded = apply_filters(
			'minit-content-' . $this->extension,
			implode( "\n\n", $done ),
			$done
		);

		// Store the combined file on the filesystem.
		$this->file_cache()->set( $cache_filename, $done_imploded );

		// Allow other plugins to do something with the resulting URL.
		$combined_file_url = apply_filters(
			'minit-url-' . $this->extension,
			$this->file_cache()->file_url( $cache_filename ),
			$done
		);

		// Cache this set of scripts, by default for 24 hours.
		$cache_ttl = apply_filters(
			'minit-cache-expiration',
			24 * 60 * 60
		);

		$cache_ttl = apply_filters(
			'minit-cache-expiration-' . $this->extension,
			$cache_ttl
		);

		$this->set_cache(
			$cache_ver,
			array(
				'done' => array_keys( $done ),
				'url' => $combined_file_url,
				'file' => $cache_filename,
			),
			$cache_ttl
		);

		return $combined_file_url;
	}

	abstract public function process( $todo );

	/**
	 * Process the contents of each file.
	 *
	 * @param  string $source Asset source
	 * @param  string $handle Asset handle
	 * @param  string $src    Relative URL of the asset
	 *
	 * @return string         Asset source
	 */
	public function minit_item( $source, $handle, $src ) {
		return $source;
	}

	/**
	 * Mark these handles as processed by Minit. Note that `done` doesn't mean
	 * that the resulting script has been printed to the page.
	 *
	 * @param array $handles List of asset handles
	 *
	 * @return void
	 */
	protected function mark_done( $handles ) {
		// Mark these as done since we'll take care of them
		$this->handler->done = array_merge(
			$this->handler->done,
			$handles
		);

		// Remove processed items from the queue
		$this->queue = array_diff(
			$this->queue,
			$handles
		);

		// Mark them as processed by Minit
		$this->done = array_merge(
			$this->done,
			$handles
		);
	}

	/**
	 * Return asset URL relative to the `base_url`.
	 *
	 * @param string $handle Asset handle
	 *
	 * @return string|boolean Asset URL relative to the base URL or `false` if not found
	 */
	protected function get_asset_relative_path( $handle ) {
		if ( ! isset( $this->handler->registered[ $handle ] ) ) {
			return false;
		}

		if ( ! empty( $this->handler->registered[ $handle ]->src ) ) {
			$item_url = $this->handler->registered[ $handle ]->src;

			// Inline block scripts are sometimes relative URLs.
			if ( 0 === strpos( $item_url, '/' ) ) {
				return $item_url;
			}

			// Remove protocol reference from the local base URL
			$base_url = preg_replace( '/^(https?:)/i', '', $this->handler->base_url );

			// Check if this is a local asset which we can include
			$src_parts = explode( $base_url, $item_url );

			// Get the trailing part of the local URL
			if ( ! empty( $src_parts ) ) {
				return array_pop( $src_parts );
			}
		}

		return false;
	}

	/**
	 * Cache for references to minited resources.
	 *
	 * @param  string $key Cache Key.
	 *
	 * @return mixed
	 */
	public function get_cache( $key ) {
		return get_transient( $this->prefix_cache_key( $key ) );
	}

	/**
	 * Cache references to minited resources.
	 *
	 * @param string  $key   Cache key.
	 * @param mixed   $value Value to cache.
	 * @param integer $ttl   Time to live or cache expiration.
	 *
	 * @return boolean
	 */
	public function set_cache( $key, $value, $ttl = 3600 ) {
		return set_transient( $this->prefix_cache_key( $key ), $value, $ttl );
	}

	/**
	 * Prefix all cache keys with a generic key.
	 *
	 * @param  string $key Cache key.
	 *
	 * @return string
	 */
	protected function prefix_cache_key( $key ) {
		return sprintf( 'minit-%s', $key );
	}
}
