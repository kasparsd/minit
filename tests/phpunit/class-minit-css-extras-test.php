<?php

/**
 * Styles with print-affecting extras must not be silently bundled.
 *
 * Bundled handles are marked as done in WP_Styles and therefore never
 * reach do_item(), which is the only place WordPress prints RTL variants
 * for styles. Any attribute that changes printing must either keep the
 * handle unbundled or invalidate the bundle cache key when it changes.
 */
class Minit_CSS_Extras_Test extends WP_UnitTestCase {

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
		remove_all_filters( 'print_scripts_array' );
	}

	protected function new_minit_css() {
		$this->minit_css = new Minit_Css(
			Minit_Plugin::instance(),
			new Minit_Asset_Cache( '/minit-css-extras-test', 'version' )
		);

		$this->minit_css->init();

		return $this->minit_css;
	}

	protected function fixture_url( $file ) {
		return sprintf( '%s/wp-content/plugins/minit/tests/fixtures/%s', site_url(), $file );
	}

	/**
	 * RTL variants are printed by WordPress in place of the plain link,
	 * which only happens for handles outside of the bundle.
	 */
	public function test_rtl_variants_are_printed() {
		$GLOBALS['wp_styles']->text_direction = 'rtl';

		$this->new_minit_css();

		wp_enqueue_style( 'minit-rtl', $this->fixture_url( 'a.css' ) );
		wp_style_add_data( 'minit-rtl', 'rtl', 'replace' );

		ob_start();
		wp_styles()->do_items( array( 'minit-rtl' ) );
		$html = (string) ob_get_clean();

		$bundle_position = strpos( $html, 'minit-css-css' );
		$rtl_position    = strpos( $html, '-rtl-css' );

		// The RTL variant is printed, not silently dropped.
		$this->assertNotFalse( $rtl_position, 'RTL variant of the stylesheet is printed' );

		// The plain LTR link is replaced by the RTL variant.
		$this->assertStringContainsString( "id='minit-rtl-rtl-css'", $html, 'RTL variant link printed in place of the plain link' );
		$this->assertStringNotContainsString( "id='minit-rtl-css'", $html, 'plain LTR link replaced by the RTL variant' );

		// Bundle is printed before the RTL variant.
		$this->assertLessThan( $rtl_position, $bundle_position, 'bundle printed before the RTL variant' );
	}

	/**
	 * Changing inline styles of a handle without bumping its version must
	 * not serve a stale bundle that hides the inline styles.
	 */
	public function test_cache_is_invalidated_when_inline_styles_change() {
		$this->new_minit_css();

		wp_enqueue_style( 'minit-simple', $this->fixture_url( 'a.css' ) );

		ob_start();
		wp_styles()->do_items( array( 'minit-simple' ) );
		$first = (string) ob_get_clean();

		// First print bundles the plain stylesheet.
		$this->assertStringContainsString( 'minit-css-css', $first, 'plain stylesheet is bundled' );

		// New print cycle with the same handle, same version and inline styles.
		remove_all_filters( 'print_styles_array' );

		$GLOBALS['wp_styles'] = new WP_Styles();
		$this->new_minit_css();

		wp_enqueue_style( 'minit-simple', $this->fixture_url( 'a.css' ) );
		wp_add_inline_style( 'minit-simple', '.simple-after-rule {}' );

		ob_start();
		wp_styles()->do_items( array( 'minit-simple' ) );
		$second = (string) ob_get_clean();

		// The handle must be excluded from the bundle and printed with its
		// inline styles instead of hiding them in a cached bundle.
		$this->assertStringContainsString( '.simple-after-rule', $second, 'inline styles printed after cache refresh' );
		$this->assertStringNotContainsString( 'minit-css-css', $second, 'changed handle not served from the stale bundle' );
	}
}
