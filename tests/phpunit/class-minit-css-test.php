<?php

class Minit_CSS_Test extends WP_UnitTestCase {
	public function test_media_query_excluded() {
		$minit_css = new Minit_Css(
			Minit_Plugin::instance(),
			new Minit_Asset_Cache( '/cache/to/minit-test', 'version' )
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

	private function url_to( string $pattern ): string {
		return sprintf( $pattern, home_url() );
	}

	public function test_resolve_urls() {
		$minit_css = new Minit_Css(
			Minit_Plugin::instance(),
			new Minit_Asset_Cache( '/resolve/urls/minit-test', 'version' )
		);

		wp_enqueue_style( 'minit-css-url-paths', 'https://example.com/default.css' );

		$this->assertEquals(
			$this->url_to( 'body { background-image: url(\'%s/path/to/path/to/image.png\'); }' ),
			$minit_css->minit_item( 'body { background-image: url( "path/to/image.png" ); }', 'minit-css', '/path/to/css.css' ),
			'relative paths wrapped in quotes'
		);

		$this->assertEquals(
			$this->url_to( 'body { background-image: url(\'%s/path/to/direct/image.png\'); }' ),
			$minit_css->minit_item( 'body { background-image: url(direct/image.png); }', 'minit-css', '/path/to/css.css' ),
			'relative paths without quotes'
		);

		$this->assertEquals(
			$this->url_to( 'body { background-image: url(\'%s/path/to/some/image.jpeg\'); }' ),
			$minit_css->minit_item( 'body { background-image: url(  "some/image.jpeg\'    ); }', 'minit-css', '/path/to/css.css' ),
			'mixed quotes and spaces'
		);

		$this->assertEquals(
			'body { background-image: url( data:image/gif;base64,R0lGODlhEAAQAMQAAO ); }',
			$minit_css->minit_item( 'body { background-image: url( data:image/gif;base64,R0lGODlhEAAQAMQAAO ); }', 'minit-css', '/path/to/css.css' ),
			'data uris are kept intact'
		);

		$this->assertEquals(
			'body { background-image: url( "http://example.jpeg" ); }',
			$minit_css->minit_item( 'body { background-image: url( "http://example.jpeg" ); }', 'minit-css', '/path/to/css.css' ),
			'absolute urls are kept intact'
		);
	}

	public function test_resolve_imports() {
		$minit_css = new Minit_Css(
			Minit_Plugin::instance(),
			new Minit_Asset_Cache( '/resolve/imports/minit-test', 'version' )
		);

		wp_enqueue_style( 'minit-css-imports', 'https://example.com/imports.css' );

		$this->assertEquals(
			$this->url_to( '@import url(\'%s/path/to/my-imported-styles.css\');' ),
			$minit_css->minit_item( '@import "my-imported-styles.css";', 'minit-css', '/path/to/my-imported-styles.css' ),
			'relative imports are made absolute'
		);

		$this->assertEquals(
			$this->url_to( '@import url(\'%s/path/relative/relative/path.css\');' ),
			$minit_css->minit_item( '@import url("relative/path.css");', 'minit-css', '/path/relative/path.css' ),
			'relative url() imports are made absolute'
		);
	}
}
