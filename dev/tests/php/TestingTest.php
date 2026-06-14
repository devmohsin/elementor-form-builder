<?php

use PHPUnit\Framework\TestCase;

class TestingTest extends TestCase {

	public function test_testing_mode_active_in_bootstrap() : void {
		$this->assertTrue( CLEFA_Testing::is_active() );
	}

	public function test_runtime_enable() : void {
		CLEFA_Testing::enable();
		$this->assertTrue( CLEFA_Testing::is_active() );
	}

	public function test_php_coverage_map_is_array() : void {
		$map = CLEFA_Testing::get_php_coverage_map();
		$this->assertIsArray( $map );
		$this->assertNotEmpty( $map );
		foreach ( $map as $row ) {
			$this->assertArrayHasKey( 'class', $row );
			$this->assertArrayHasKey( 'has_test', $row );
		}
	}

	public function test_js_coverage_map_is_array() : void {
		$map = CLEFA_Testing::get_js_coverage_map();
		$this->assertIsArray( $map );
		$this->assertNotEmpty( $map );
	}
}
