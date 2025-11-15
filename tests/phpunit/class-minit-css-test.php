<?php

class Minit_CSS_Test extends WP_UnitTestCase {
	public function test_media_query_excluded() {
		$minit_css = new Minit_Css(
			Minit_Plugin::instance(),
			new Minit_Asset_Cache( WP_CONTENT_DIR . '/minit-test', 'version' )
		);

		wp_enqueue_style( 'minit-css-media-default', 'https://example.com/default.css' );
		wp_enqueue_style( 'minit-css-media-screen', 'https://example.com/screen.css', array(), null, 'screen' );
		wp_enqueue_style( 'minit-css-media-print', 'https://example.com/print.css', array(), null, 'print' );

		$this->assertEquals(
			'body {}',
			$minit_css->minit_item( 'body {}', 'minit-css-media-default', '/path/to/default.css' ),
			'stylesheets with no media query (default) are included in minit'
		);

		$this->assertEquals(
			'body {}',
			$minit_css->minit_item( 'body {}', 'minit-css-media-screen', '/path/to/screen.css' ),
			'stylesheets with screen media query are included in minit'
		);

		$this->assertFalse(
			$minit_css->minit_item( 'body {}', 'minit-css-media-print', '/path/to/print.css' ),
			'stylesheets with non-screen media queries are excluded from minit'
		);
	}

	public function test_resolve_urls() {
		$minit_css = new Minit_Css(
			Minit_Plugin::instance(),
			new Minit_Asset_Cache( WP_CONTENT_DIR . '/minit-test', 'version' )
		);

		wp_enqueue_style( 'minit-css-media-default', 'https://example.com/default.css' );

		$this->assertEquals(
			'body { background-image: url(\'http://localhost:8888/path/to/path/to/image.png\'); }',
			$minit_css->minit_item( 'body { background-image: url( "path/to/image.png" ); }', 'minit-css', '/path/to/css.css' ),
			'stylesheets with no media query (default) are included in minit'
		);
	}
}
