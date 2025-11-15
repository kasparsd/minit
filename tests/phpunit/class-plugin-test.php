<?php

class Plugin_Test extends WP_UnitTestCase {
	public function test_minit_plugin_loaded() {
		$this->assertTrue( class_exists( Minit_Plugin::class ) );
	}
}
