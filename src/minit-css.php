<?php

class Minit_Css extends Minit_Assets {

	/**
	 * Asset handle key.
	 *
	 * @var string
	 */
	const ASSET_HANDLE = 'minit-css';

	protected $plugin;

	protected $cache;

	public function __construct( $plugin, $cache ) {
		$this->plugin = $plugin;
		$this->cache = $cache;

		parent::__construct( wp_styles(), 'css', $plugin->revision );
	}

	public function file_cache() {
		return $this->cache;
	}

	public function init() {
		// Queue all assets
		add_filter( 'print_styles_array', array( $this, 'register' ) );

		// Print our CSS files
		add_filter( 'print_styles_array', array( $this, 'process' ), 20 );
	}

	public function process( $todo ) {
		// Put back handlers that were excluded from Minit
		$todo = array_merge(
			$todo,
			$this->queue
		);

		$url = $this->minit();

		// Everything still in the queue has been accounted for: bundled
		// handles are marked as done, the rest is in $todo and prints as is.
		// Clear it so the next printing burst of this request starts from
		// clean state instead of re-merging stale handles.
		$deps = $this->queue;
		$this->queue = array();

		if ( empty( $url ) ) {
			return $todo;
		}

		wp_enqueue_style(
			self::ASSET_HANDLE,
			$url,
			$deps,
			null // We use filenames for versioning.
		);

		/**
		 * Add our Minit style since wp_enqueue_style won't do it at this point.
		 *
		 * Place it at the position of the first handle that was bundled, so
		 * stylesheets excluded from the bundle keep printing before it when
		 * they were enqueued earlier. Previously the bundle was placed before
		 * everything, which let excluded stylesheets (like a parent theme
		 * stylesheet printed separately because it has inline styles) cascade
		 * after the bundle and override bundled styles such as dark mode
		 * rules.
		 */
		return $this->merge_print_order( (array) $todo );
	}

	/**
	 * Reconstruct the full print order with the bundle handle placed at the
	 * position of its first bundled member.
	 *
	 * Bundled handles are already marked as done and get skipped by
	 * WP_Styles::do_items() when printing, so leaving them in the list is
	 * harmless and keeps the list complete.
	 *
	 * @param string[] $todo Handles up for printing: those excluded from
	 *                       the bundle and any leftovers from the queue.
	 *
	 * @return string[]
	 */
	protected function merge_print_order( array $todo ) {
		$order = (array) $this->print_order;

		// Make sure every handle up for printing remains in the list.
		foreach ( $todo as $handle ) {
			// The bundle handle can reach later filter runs once it has been
			// enqueued for an earlier burst, which would add a duplicate.
			if ( self::ASSET_HANDLE === $handle ) {
				continue;
			}

			if ( ! in_array( $handle, $order, true ) ) {
				$order[] = $handle;
			}
		}

		// Find the first bundled handle in the print order.
		$position = null;

		foreach ( $order as $index => $handle ) {
			if ( in_array( $handle, $this->done, true ) ) {
				$position = $index;
				break;
			}
		}

		if ( null === $position ) {
			// Near-unreachable in normal flow: register() always runs before
			// handles get marked as done. Kept as a fallback so the bundle
			// still prints instead of being dropped entirely.
			array_unshift( $order, self::ASSET_HANDLE );
			return $order;
		}

		array_splice( $order, $position, 0, self::ASSET_HANDLE );

		return $order;
	}

	public function minit_item( $content, $handle, $src ) {
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

	protected function resolve_urls( $content, $handle, $src ) {
		if ( ! $content ) {
			return $content;
		}

		// Make all local asset URLs absolute
		$content = preg_replace_callback(
			'/url\(\s*(?!["\'\s]*(?:data:|https?:|\/\/))(.*?)\s*\)/i',
			fn ( $matches ) => sprintf(
				"url('%s/%s')",
				$this->handler->base_url . dirname( $src ),
				trim( $matches[1], '\'" ' )
			),
			$content
		);

		return $content;
	}

	protected function resolve_imports( $content, $handle, $src ) {
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

	protected function exclude_with_media_query( $content, $handle, $src ) {
		if ( ! $content ) {
			return $content;
		}

		// Exclude from Minit if non-screen media query specified.
		if ( ! empty( $this->handler->registered[ $handle ]->args ) && ! in_array( $this->handler->registered[ $handle ]->args, array( 'all', 'screen' ), true ) ) {
			return false;
		}

		// Exclude all items with conditionals.
		if ( ! empty( $this->handler->registered[ $handle ]->extra['conditional'] ) ) {
			return false;
		}

		// Exclude styles with RTL variants: their -rtl.css file is printed
		// by WordPress only when the item itself is not bundled.
		if ( ! empty( $this->handler->registered[ $handle ]->extra['rtl'] ) ) {
			return false;
		}

		// Exclude styles with inline "after" CSS.
		$inline_styles = $this->handler->get_data( $handle, 'after' );

		if ( ! empty( $inline_styles ) ) {
			return false;
		}

		return $content;
	}
}
