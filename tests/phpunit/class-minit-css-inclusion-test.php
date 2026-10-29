<?php

/**
 * End-to-end verification of how handles excluded from bundling are printed.
 *
 * Runs the real WP_Styles flow: all_deps() applies print_styles_array,
 * which chains Minit's register() (10) and process() (20).
 */
class Minit_CSS_Inclusion_Test extends WP_UnitTestCase {

	/**
	 * @var Minit_Css
	 */
	protected $minit_css;

	public function set_up(): void {
		parent::set_up();

		// Fresh styles handler per test to avoid shared queue state.
		$GLOBALS['wp_styles'] = new WP_Styles();

		// Discard hooks from the plugin bootstrap instance, which still
		// references the old handler instance.
		remove_all_filters( 'print_styles_array' );

		$this->minit_css = new Minit_Css(
			Minit_Plugin::instance(),
			new Minit_Asset_Cache( '/minit-css-inclusion-test', 'version' )
		);

		$this->minit_css->init();
	}

	/**
	 * Fixture markup fragments in the order they must be printed.
	 *
	 * @return string[]
	 */
	protected function expected_fragments() {
		return array(
			'bundle'    => sprintf( 'id=' . "'%s'", 'minit-css-css' ), // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
			'b_file'    => 'b.css',
			'b_after'   => '.b-after-rule',
			'c_file'    => 'c.css',
		);
	}

	protected function enqueue_fixture_styles() {
		$base = sprintf( '%s/wp-content/plugins/minit/tests/fixtures', site_url() );

		wp_enqueue_style( 'minit-simple', $base . '/a.css' );

		wp_enqueue_style( 'minit-inline-after', $base . '/b.css' );
		wp_add_inline_style( 'minit-inline-after', '.b-after-rule { color: green; }' );

		wp_enqueue_style( 'minit-print', $base . '/c.css', array(), null, 'print' );
	}

	/**
	 * Print the queue through the real print_styles_array filter chain.
	 *
	 * @return string Printed markup.
	 */
	protected function print_queue() {
		ob_start();

		wp_styles()->do_items( array( 'minit-simple', 'minit-inline-after', 'minit-print' ) );

		return (string) ob_get_clean();
	}

	/**
	 * Styles excluded from the bundle by Minit_CSS must still be printed by
	 * WordPress, each followed by its inline "after" data, in the original
	 * enqueue order and after the bundle.
	 */
	public function test_excluded_styles_are_printed_in_order() {
		$this->enqueue_fixture_styles();

		$html = $this->print_queue();

		$fragments = $this->expected_fragments();

		// None of the styles is dropped from the output.
		foreach ( $fragments as $label => $fragment ) {
			$this->assertStringContainsString(
				$fragment,
				$html,
				sprintf( 'printed output contains "%s"', $label )
			);
		}

		// The stylesheet with inline "after" data is not bundled.
		$this->assertStringContainsString( 'href', $this->anchor_fragment( $html, 'minit-inline-after' ) );

		// Bundle is printed before the excluded stylesheet, which in turn is
		// followed by its inline styles.
		$positions = array();

		foreach ( $fragments as $label => $fragment ) {
			$positions[ $label ] = strpos( $html, $fragment );
		}

		$this->assertLessThan( $positions['b_file'], $positions['bundle'], 'bundle printed before the excluded stylesheet' );
		$this->assertLessThan( $positions['b_after'], $positions['b_file'], 'inline after styles printed following their stylesheet' );
		$this->assertLessThan( $positions['c_file'], $positions['b_after'], 'enqueued order is maintained' );
	}

	/**
	 * Print-media styles excluded from bundle are printed separately, not
	 * silently dropped.
	 */
	public function test_print_media_styles_are_printed() {
		$this->enqueue_fixture_styles();

		$html = $this->print_queue();

		$print_link = $this->anchor_fragment( $html, 'minit-print' );

		$this->assertNotEmpty( $print_link, 'print media stylesheet link is printed' );
		$this->assertStringContainsString( "media='print'", $print_link, 'print media attribute preserved' );
	}

	/**
	 * Extract the full `<link>` markup for a given handle.
	 *
	 * @param  string $html   Printed markup.
	 * @param  string $handle Handle to extract.
	 *
	 * @return string
	 */
	protected function anchor_fragment( $html, $handle ) {
		if ( ! preg_match(
			sprintf( '/<link[^>]+id=%s%s-css[^>]+>/i', "['\"]", preg_quote( $handle, '/' ) ),
			$html,
			$matches
		) ) {
			return '';
		}

		return $matches[0];
	}
}
