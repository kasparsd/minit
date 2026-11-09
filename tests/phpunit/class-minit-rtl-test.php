<?php

/**
 * Stylesheets with RTL variants must print their -rtl variant and stay out
 * of the bundle: the variant is only printed by WP_Styles::do_item(), which
 * bundled handles never reach.
 */
class Minit_RTL_Test extends WP_UnitTestCase {

	/**
	 * @var Minit_Css
	 */
	protected $minit_css;

	public function set_up(): void {
		parent::set_up();

		// Fresh styles handler per test to avoid shared queue state.
		$GLOBALS['wp_styles'] = new WP_Styles();
		$GLOBALS['wp_styles']->text_direction = 'rtl';

		// Discard hooks from the plugin bootstrap instance, which still
		// references the old handler instance.
		remove_all_filters( 'print_styles_array' );
		remove_all_filters( 'print_scripts_array' );

		$this->minit_css = new Minit_Css(
			Minit_Plugin::instance(),
			new Minit_Asset_Cache( '/minit-rtl-test', 'version' )
		);

		$this->minit_css->init();
	}

	public function test_rtl_variant_is_printed_and_not_bundled() {
		$base = sprintf( '%s/wp-content/plugins/minit/tests/fixtures', site_url() );

		wp_enqueue_style( 'minit-rtl', $base . '/a.css' );
		wp_style_add_data( 'minit-rtl', 'rtl', 'replace' );

		ob_start();

		wp_styles()->do_items( array( 'minit-rtl' ) );

		$html = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $html, 'a-rtl.css' ), 'RTL variant printed' );
		$this->assertStringNotContainsString( 'minit-css-css', $html, 'nothing was bundled' );
		$this->assertEmpty( $this->minit_css->done, 'RTL handles stay out of the bundle' );
	}
}
