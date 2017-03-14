<?php

class Minit_Js extends Minit_Assets {

	private $plugin;


	function __construct( $plugin ) {

		$this->plugin = $plugin;

		parent::__construct( wp_scripts(), 'js', $plugin->revision );

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


	function process( $todo ) {

		// Run this only in the footer
		if ( ! did_action( 'wp_print_footer_scripts' ) ) {
			return $todo;
		}

		// Put back handlers that were excluded from Minit
		$todo = array_merge( $todo, $this->queue );
		$handle = 'minit-js';
		$url = $this->minit();

		if ( empty( $url ) ) {
			return $todo;
		}

		// @todo create a fallback for apply_filters( 'minit-js-in-footer', true )
		wp_register_script( $handle, $url, null, null, true );

		// Add our Minit script since wp_enqueue_script won't do it at this point
		$todo[] = $handle;

		$extra = array(
			'data' => array(),
			'before' => array(),
			'after' => array(),
		);

		// Add inline scripts for all minited scripts
		foreach ( $this->done as $script ) {
			$extra['data'][] = $this->handler->get_data( $script, 'data' );
			$extra['before'][] = $this->handler->get_data( $script, 'before' );
			$extra['after'][] = $this->handler->get_data( $script, 'after' );
		}

		foreach ( $extra as $extra_key => $extra_data ) {
			// Remove elements that don't have anything
			$extra_data = array_values( array_filter( $extra_data ) );

			if ( empty( $extra_data ) ) {
				continue;
			}

			if ( is_string( $extra_data[0] ) ) {
				$this->handler->add_data( $handle, $extra_key, implode( "\n", $extra_data ) );
			} elseif ( is_array( $extra_data[0] ) ) {
				foreach ( $extra_data as $extra_data_set ) {
					$this->handler->add_data( $handle, $extra_key, $extra_data_set );
				}
			}
		}

		return $todo;

	}


	public function print_async_scripts() {

		$async_queue = array();
		$minit_exclude = (array) apply_filters( 'minit-exclude-js', array() );

		foreach ( $this->handler->queue as $handle ) {

			// Skip asyncing explicitly excluded script handles
			if ( in_array( $handle, $minit_exclude ) ) {
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

		?>
		<!-- Asynchronous scripts by Minit -->
		<script id="minit-async-scripts" type="text/javascript">
		(function() {
			var js, fjs = document.getElementById('minit-async-scripts'),
				add = function( url, id ) {
					js = document.createElement('script');
					js.type = 'text/javascript';
					js.src = url;
					js.async = true;
					js.id = id;
					fjs.parentNode.insertBefore(js, fjs);
				};
			<?php
			foreach ( $async_queue as $handle ) {
				printf(
					'add( "%s", "%s" ); ',
					esc_js( $this->handler->registered[ $handle ]->src ),
					'async-script-' . esc_attr( $handle )
				);
			}
			?>
		})();
		</script>
		<?php

	}


	public function script_tag_async( $tag, $handle, $src ) {

		// Allow others to enable this feature
		if ( ! apply_filters( 'minit-script-tag-async', false ) ) {
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
