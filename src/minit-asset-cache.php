<?php

class Minit_Asset_Cache {

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

	/**
	 * URL of the cache directory.
	 *
	 * @var string
	 */
	protected $url;

	public function __construct( $directory, $version_key ) {
		$this->version_key = $version_key;

		$upload_dir = wp_upload_dir( null, false ); // Don't create the directory.

		$this->dir = sprintf( '%s/%s', $upload_dir['basedir'], $directory );
		$this->url = sprintf( '%s/%s', $upload_dir['baseurl'], $directory );

		// Ensure the directory exists.
		if ( ! file_exists( $this->dir ) ) {
			wp_mkdir_p( $this->dir );
		}
	}

	/**
	 * Helper for getting the absolute directory path of the cache folder.
	 *
	 * @return string
	 */
	public function dir() {
		return $this->dir;
	}

	/**
	 * Get an absolute file path relative to the cache directory.
	 *
	 * @param  string $path Relative file path.
	 *
	 * @return string
	 */
	public function file_path( $path ) {
		return sprintf( '%s/%s', $this->dir, ltrim( $path, '/' ) );
	}

	/**
	 * Get a URL of a cache file relative to the cache directory.
	 *
	 * @param  string $path File path.
	 *
	 * @return string
	 */
	public function file_url( $path ) {
		return sprintf( '%s/%s', $this->url, ltrim( $path, '/' ) );
	}

	/**
	 * Get a cache file from the disk.
	 *
	 * @param  string $path File path relative to the cache directory.
	 *
	 * @return string|null
	 */
	public function get( $path ) {
		$file = $this->file_path( $path );

		if ( file_exists( $file ) ) {
			return file_get_contents( $file );
		}

		return null;
	}

	/**
	 * Store in the cache.
	 *
	 * @param string $path    File path relative to the cache directory.
	 * @param string $content Contents to store.
	 *
	 * @return integer|boolean Number of bytes written or boolean false on failure.
	 */
	public function set( $path, $content ) {
		return file_put_contents( $this->file_path( $path ), $content );
	}

	/**
	 * Get the current cache version.
	 *
	 * @return string|null
	 */
	public function version() {
		$version = get_option( $this->version_key );

		if ( empty( $version ) ) {
			return null;
		}

		return $version;
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
