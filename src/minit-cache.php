<?php

class Minit_Cache {

	/**
	 * Option key used for storing the cache version string.
	 *
	 * @var string
	 */
	protected $version_key;

	/**
	 * Absolute path to the cache directory.
	 *
	 * @var string
	 */
	protected $dir;

	public function __construct( $directory, $version_key ) {
		$this->dir = $directory;
		$this->version_key = $version_key;

		if ( ! file_exists( $directory ) ) {
			wp_mkdir_p( $directory );
		}
	}

	public function dir() {
		return $this->dir;
	}

	/**
	 * Bump the cache version.
	 *
	 * @return void
	 */
	public function bump() {
		// Use this as a global cache version number
		update_option( $this->version_key, time() );

		// Allow other plugins to know that we purged
		do_action( 'minit-cache-purged', $this->version_key ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Delete cache files and bump the cache version.
	 *
	 * @return void
	 */
	public function purge() {
		foreach ( $this->files() as $file ) {
			unlink( $file );
		}

		$this->bump();
	}

	/**
	 * Get all files in the cache.
	 *
	 * @return array
	 */
	public function files() {
		$files = glob( $this->dir() . '/*', GLOB_NOSORT );

		if ( ! empty( $files ) && is_array( $files ) ) {
			return $files;
		}

		return array();
	}

}
