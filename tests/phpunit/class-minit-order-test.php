<?php

/**
 * Ordering, printing and multi-burst behavior of bundles.
 */
class Minit_Order_Test extends WP_UnitTestCase {

	/**
	 * @var Minit_Css
	 */
	protected $minit_css;

	/**
	 * @var Minit_Js
	 */
	protected $minit_js;

	/**
	 * @var string Base URL of the fixture directory.
	 */
	protected $base;

	public function set_up(): void {
		parent::set_up();

		// Fresh handlers per test to avoid shared queue state.
		$GLOBALS['wp_styles']  = new WP_Styles();
		$GLOBALS['wp_scripts'] = new WP_Scripts();

		// Discard hooks from the plugin bootstrap instance, which still
		// references the old handler instances.
		remove_all_filters( 'print_styles_array' );
		remove_all_filters( 'print_scripts_array' );

		$this->base = sprintf( '%s/wp-content/plugins/minit/tests/fixtures', site_url() );

		$this->minit_css = new Minit_Css(
			Minit_Plugin::instance(),
			new Minit_Asset_Cache( '/minit-css-order-test', 'version' )
		);
		$this->minit_css->init();

		$this->minit_js = new Minit_Js(
			Minit_Plugin::instance(),
			new Minit_Asset_Cache( '/minit-js-order-test', 'version' )
		);
		$this->minit_js->init();
	}

	/**
	 * JS: every enqueued script must print exactly once. Header scripts are
	 * not printed raw in the header burst: they keep waiting in the queue
	 * and end up (once) in the deferred footer bundle.
	 */
	public function test_header_and_footer_scripts_are_bundle_once() {
		wp_enqueue_script( 'minit-js-header', $this->base . '/a.js', array(), null, false );
		wp_enqueue_script( 'minit-js-footer', $this->base . '/b.js', array(), null, true );

		// Header printing burst: only bundling in the footer.
		ob_start();
		wp_scripts()->do_items( array( 'minit-js-header', 'minit-js-footer' ), 0 );
		$head = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script', $head, 'nothing bundles in the header burst' );

		// Footer printing burst through the real footer machinery.
		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$footer = (string) ob_get_clean();

		// Everything was bundled: exactly one script tag, the bundle itself.
		$this->assertSame( 1, substr_count( $head . $footer, '<script' ), 'exactly one script tag printed' );
		$this->assertSame( 1, substr_count( $head . $footer, 'minit-js-js' ), 'bundle printed once' );
		$this->assertSame( array( 'minit-js-header', 'minit-js-footer' ), array_values( $this->minit_js->done ), 'header and footer scripts bundled' );
	}

	/**
	 * JS: scripts that arrive after the bundle was printed must still print
	 * (as raw tags) instead of being merged into an unprintable second
	 * bundle. Previously they were bundled, marked done and silently lost.
	 */
	public function test_late_scripts_are_printed_after_the_bundle() {
		wp_enqueue_script( 'minit-js-first', $this->base . '/a.js', array(), null, true );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		ob_get_clean();

		$this->assertNotEmpty( $this->minit_js->done, 'first script was bundled' );

		wp_enqueue_script( 'minit-js-late', $this->base . '/c.js', array(), null, true );

		ob_start();
		wp_scripts()->do_footer_items();
		$late = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $late, '<script' ), 'exactly one new script tag printed' );
		$this->assertSame(
			1,
			substr_count( $late, 'c.js' ),
			'late script printed raw, not swallowed by a second bundle'
		);
		$this->assertStringNotContainsString( 'minit-js-js', $late, 'no second bundle printed' );

		// A third burst with yet another late script keeps printing raw.
		wp_enqueue_script( 'minit-js-later', $this->base . '/a.js', array(), null, true );

		ob_start();
		wp_scripts()->do_footer_items();
		$later = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $later, '<script' ), 'exactly one new script tag printed in the third burst' );
		$this->assertSame( 1, substr_count( $later, 'a.js' ), 'third-burst script printed raw' );
		$this->assertStringNotContainsString( 'minit-js-js', $later, 'still no second bundle printed' );
	}

	/**
	 * CSS: a non-local stylesheet enqueued between bundled handles stays in
	 * the cascade after the bundle (bundled handles cannot be split around
	 * it) and prints exactly once.
	 */
	public function test_css_order_with_non_local_stylesheet_in_between() {
		wp_enqueue_style( 'minit-first-local', $this->base . '/a.css' );
		wp_enqueue_style( 'minit-cdn', 'https://cdn.example.com/styles.css' );
		wp_enqueue_style( 'minit-second-local', $this->base . '/b.css' );

		$html = $this->print_queue();

		$positions = array(
			'bundle' => strpos( $html, sprintf( 'id=' . "'%s'", 'minit-css-css' ) ), // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
			'cdn'    => strpos( $html, 'cdn.example.com' ),
		);

		$this->assertSame( 1, substr_count( $html, 'minit-css-css' ), 'bundle printed once' );
		$this->assertSame( 1, substr_count( $html, 'cdn.example.com' ), 'non-local stylesheet printed once' );
		$this->assertGreaterThan( -1, $positions['bundle'], 'bundle printed' );
		$this->assertGreaterThan( -1, $positions['cdn'], 'non-local stylesheet printed' );
		$this->assertLessThan( $positions['cdn'], $positions['bundle'], 'bundle printed before the non-local stylesheet' );
		$this->assertStringNotContainsString( 'b.css', $html, 'second local handle bundled, not printed raw' );
	}

	/**
	 * CSS: styles enqueued after the bundle was printed must still print as
	 * raw tags. Previously they were bundled again, the resulting URL was
	 * never registered by WordPress and they silently disappeared.
	 */
	public function test_late_styles_are_printed_after_the_bundle() {
		wp_enqueue_style( 'minit-late-first', $this->base . '/a.css' );

		$this->print_queue();

		$this->assertNotEmpty( $this->minit_css->done, 'first stylesheet was bundled' );

		wp_enqueue_style( 'minit-late', $this->base . '/c.css' );

		$late = $this->print_queue( array( 'minit-late' ) );

		$this->assertSame(
			1,
			substr_count( $late, 'c.css' ),
			'late stylesheet printed raw, not swallowed by a second bundle'
		);
		$this->assertStringNotContainsString( 'minit-css-css', $late, 'no second bundle printed' );
	}

	/**
	 * Print the style queue through the real print_styles_array filter chain.
	 *
	 * @param string[]|null $handles Handles to print, defaults to the fixtures enqueued so far.
	 *
	 * @return string Printed markup.
	 */
	protected function print_queue( ?array $handles = null ) {
		if ( null === $handles ) {
			$handles = array();

			foreach ( wp_styles()->queue as $handle ) {
				$handles[] = $handle;
			}
		}

		ob_start();

		wp_styles()->do_items( $handles );

		return (string) ob_get_clean();
	}
}
