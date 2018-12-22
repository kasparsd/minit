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

	function __construct( $plugin, $cache ) {
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

		// Print external scripts asynchronously in the footer
		add_action( 'wp_print_footer_scripts', array( $this, 'print_async_scripts' ), 20 );

		// Load our JS files asynchronously
		add_filter( 'script_loader_tag', array( $this, 'script_tag_async' ), 20, 3 );
	}

	public function process( $todo ) {
		// TODO: Allow disabling the forced footer placement for scripts.
		// $force_footer = apply_filters( 'minit-js-force-footer', true );

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

		// @todo create a fallback for apply_filters( 'minit-js-in-footer', true )
		wp_register_script(
			self::ASSET_HANDLE,
			$url,
			[],
			null, // We use filenames for versioning.
			true // Place in the footer.
		);

		// Add our Minit script since wp_enqueue_script won't do it at this point
		$todo[] = self::ASSET_HANDLE;

		// Merge all the custom before, after anda data extras with our minit file.
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
			$this->handler->add_data( self::ASSET_HANDLE, 'after', $extra['after'] );
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


	public function print_async_scripts() {
		$async_queue = array();

		$minit_exclude = (array) apply_filters(
			'minit-exclude-js',
			array()
		);

		foreach ( $this->handler->queue as $handle ) {
			// Skip asyncing explicitly excluded script handles
			if ( in_array( $handle, $minit_exclude, true ) ) {
				continue;
			}

			$script_relative_path = $this->get_asset_relative_path( $handle );

			if ( ! $script_relative_path ) {
				// Add this script to our async queue
				$async_queue[] = $handle;
			}
		}

		if ( empty( $async_queue ) ) {
			return;
		}

		$scripts = array();

		foreach ( $async_queue as $handle ) {
			$scripts[] = array(
				'id' => 'async-script-' . sanitize_key( $handle ),
				'src' => $this->handler->registered[ $handle ]->src,
			);
		}

		?>
		<!-- Asynchronous scripts by Minit -->
		<script id="minit-async-scripts" type="text/javascript">
		(function() {
			var scripts = <?php echo wp_json_encode( $scripts ); ?>;
			var fjs = document.getElementById( 'minit-async-scripts' );

			function minitLoadScript( url, id ) {
				var js = document.createElement('script');
				js.type = 'text/javascript';
				js.src = url;
				js.async = true;
				js.id = id;
				fjs.parentNode.insertBefore(js, fjs);
			};

			scripts.map( function( script ) {
				minitLoadScript( script.src, script.id );
			} );
		})();
		</script>
		<?php
	}

	/**
	 * Check if the script has any "after" logic defined.
	 *
	 * @param  string  $handle Script handle.
	 *
	 * @return boolean
	 */
	public function script_has_data_after( $handle ) {
		$data_after = $this->handler->get_data( $handle, 'after' );

		return ! empty( $data_after );
	}

	/**
	 * Adjust the script tag to support asynchronous loading.
	 *
	 * @param  string $tag    Script tag.
	 * @param  string $handle Script handle or ID.
	 * @param  string $src    Script tag URL.
	 *
	 * @return string
	 */
	public function script_tag_async( $tag, $handle, $src ) {
		// Scripts with "after" logic probably depend on the parent JS.
		$enable_async = ! $this->script_has_data_after( $handle );

		// Allow others to disable this feature
		if ( ! apply_filters( 'minit-script-tag-async', $enable_async ) ) {
			return $tag;
		}

		// Do this for minit scripts only
		if ( false === stripos( $handle, 'minit-' ) ) {
			return $tag;
		}

		// Bail if async is already set
		if ( false !== stripos( $tag, ' async' ) ) {
			return $tag;
		}

		return str_ireplace( '<script ', '<script async ', $tag );
	}

}
