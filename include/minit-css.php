<?php

class Minit_Css extends Minit_Assets {

	private $plugin;


	function __construct( $plugin ) {

		$this->plugin = $plugin;

		parent::__construct( wp_styles(), 'css', $plugin->revision );

	}


	public function init() {

		// Queue all assets
		add_filter( 'print_styles_array', array( $this, 'register' ) );

		// Print our CSS files
		add_filter( 'print_styles_array', array( $this, 'process' ), 20 );

	}


	function process( $todo ) {

		// Put back handlers that were excluded from Minit
		$todo = array_merge( $todo, $this->queue );
		$handle = 'minit-css';
		$url = $this->minit();

		if ( empty( $url ) ) {
			return $todo;
		}

		wp_enqueue_style( $handle, $url, null, null );

		// Add our Minit style since wp_enqueue_script won't do it at this point
		$todo[] = $handle;

		// Add inline styles for all minited styles
		foreach ( $this->done as $script ) {

			// Can this return an array instead?
			$inline_styles = $this->handler->get_data( $script, 'after' );

			if ( ! empty( $inline_styles ) ) {
				$this->handler->add_inline_style( $handle, implode( "\n", $inline_styles ) );
			}
		}

		return $todo;

	}


	function minit_item( $content, $handle, $src ) {

		if ( empty( $content ) ) {
			return $content;
		}

		// Exclude styles with media queries from being included in Minit
		$content = $this->exclude_with_media_query( $content, $handle, $src );

		// Make all asset URLs absolute
		$content = $this->resolve_urls( $content, $handle, $src );

		// Add support for relative CSS imports
		$content = $this->resolve_imports( $content, $handle, $src );

		return $content;

	}


	private function resolve_urls( $content, $handle, $src ) {

		if ( ! $content ) {
			return $content;
		}

		// Make all local asset URLs absolute
		$content = preg_replace(
			'/url\(["\' ]?+(?!data:|https?:|\/\/)(.*?)["\' ]?\)/i',
			sprintf( "url('%s/$1')", $this->handler->base_url . dirname( $src ) ),
			$content
		);

		return $content;

	}


	private function resolve_imports( $content, $handle, $src ) {

		if ( ! $content ) {
			return $content;
		}

		// Make all import asset URLs absolute
		$content = preg_replace(
			'/@import\s+(url\()?["\'](?!https?:|\/\/)(.*?)["\'](\)?)/i',
			sprintf( "@import url('%s/$2')", $this->handler->base_url . dirname( $src ) ),
			$content
		);

		return $content;

	}


	private function exclude_with_media_query( $content, $handle, $src ) {

		if ( ! $content ) {
			return $content;
		}

		$whitelist = array( '', 'all', 'screen' );

		// Exclude from Minit if media query specified
		if ( ! in_array( $this->handler->registered[ $handle ]->args, $whitelist ) ) {
			return false;
		}

		return $content;

	}

}
