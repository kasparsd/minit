<?php

class Minit_Js extends Minit_Assets {

	/**
	 * Asset handle key.
	 *
	 * @var string
	 */
	const ASSET_HANDLE = 'minit-js';

	protected $plugin;

	protected $cache;

	public function __construct( $plugin, $cache ) {
		$this->plugin = $plugin;
		$this->cache = $cache;

		parent::__construct( wp_scripts(), 'js', $plugin->revision );
	}

	public function file_cache() {
		return $this->cache;
	}

	public function init() {
		// Queue all assets
		add_filter( 'print_scripts_array', array( $this, 'register' ) );

		// Print our JS file
		add_filter( 'print_scripts_array', array( $this, 'process' ), 20 );

		// Defer all our JS.
		add_filter( 'script_loader_tag', array( $this, 'script_tag_defer' ), 20, 3 );
	}

	public function process( $todo ) {
		// Run this only in the footer
		if ( ! did_action( 'wp_print_footer_scripts' ) ) {
			return $todo;
		}

		// Put back handlers that were excluded from Minit
		$todo = array_merge( $todo, $this->queue );
		$url = $this->minit();

		if ( empty( $url ) ) {
			return $todo;
		}

		wp_register_script(
			self::ASSET_HANDLE,
			$url,
			array(),
			null, // We use filenames for versioning.
			true // Place in the footer.
		);

		if ( apply_filters( 'minit-script-tag-async', true ) ) {
			wp_script_add_data( self::ASSET_HANDLE, 'strategy', 'defer' );
		}

		// Add our Minit script since wp_enqueue_script won't do it at this point
		$todo[] = self::ASSET_HANDLE;

		// Merge all the custom before, after and data extras with our minit file.
		$extra = $this->get_script_data(
			$this->done,
			array(
				'data',
				'before',
				'after',
			)
		);

		if ( ! empty( $extra['data'] ) ) {
			$this->handler->add_data( self::ASSET_HANDLE, 'data', implode( "\n", $extra['data'] ) );
		}

		if ( ! empty( $extra['before'] ) ) {
			$this->handler->add_data( self::ASSET_HANDLE, 'before', $extra['before'] );
		}

		if ( ! empty( $extra['after'] ) ) {
			$this->handler->add_data(
				self::ASSET_HANDLE,
				'after',
				array(
					sprintf(
						"document.getElementById( '%s' ).addEventListener( 'load', function () { %s } );",
						self::ASSET_HANDLE . '-js',
						implode( ' ', $extra['after'] )
					),
				)
			);
		}

		return $todo;
	}

	/**
	 * Get the custom data associated with each script.
	 *
	 * @param  array $handles List of script handles.
	 * @param  array $keys    List of data keys to get.
	 *
	 * @return array
	 */
	protected function get_script_data( $handles, $keys ) {
		$extra = array_combine(
			$keys,
			array_fill( 0, count( $keys ), array() ) // Creates a list of empty arrays.
		);

		foreach ( $handles as $script ) {
			foreach ( $keys as $key ) {
				$value = $this->handler->get_data( $script, $key );

				// WordPress has this strange way of adding "after" and "before".
				if ( is_array( $value ) ) {
					$extra[ $key ] = array_merge( $extra[ $key ], $value );
				} else {
					$extra[ $key ][] = $value;
				}
			}
		}

		foreach ( $extra as &$values ) {
			$values = array_filter( $values );
		}

		return $extra;
	}

	/**
	 * Fallback to defering for older versions of WP.
	 *
	 * @param  string $tag    Script tag.
	 * @param  string $handle Script handle or ID.
	 * @param  string $src    Script tag URL.
	 *
	 * @return string
	 */
	public function script_tag_defer( $tag, $handle, $src ) {
		if ( self::ASSET_HANDLE !== $handle ) {
			return $tag;
		}

		if ( ! apply_filters( 'minit-script-tag-async', true ) ) {
			return $tag;
		}

		// Bail if defered already.
		if ( false !== stripos( $tag, ' defer' ) ) {
			return $tag;
		}

		return str_ireplace( ' src=', ' defer src=', $tag );
	}
}
