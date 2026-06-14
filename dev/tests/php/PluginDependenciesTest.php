<?php

use PHPUnit\Framework\TestCase;

class PluginDependenciesTest extends TestCase {

	public function test_get_status_returns_all_keys() : void {
		$status = CLEFA_Plugin_Dependencies::get_status();
		$this->assertArrayHasKey( 'elementor', $status );
		$this->assertArrayHasKey( 'elementor_pro', $status );
		$this->assertArrayHasKey( 'acf', $status );
		$this->assertArrayHasKey( 'acf_pro', $status );
		$this->assertArrayHasKey( 'woocommerce', $status );
	}

	public function test_meets_requirements_empty() : void {
		$this->assertTrue( CLEFA_Plugin_Dependencies::meets_requirements( array() ) );
	}

	public function test_enrich_definition_marks_unavailable() : void {
		$def = CLEFA_Plugin_Dependencies::enrich_definition( array(
			'type'     => 'update_acf_field',
			'label'    => 'ACF',
			'requires' => array( 'acf_pro_only_fake_xyz' ),
		) );
		$this->assertFalse( $def['available'] );
		$this->assertNotEmpty( $def['disabled_reason'] );
	}

	public function test_get_label_for_acf() : void {
		$label = CLEFA_Plugin_Dependencies::get_label( CLEFA_Plugin_Dependencies::DEP_ACF );
		$this->assertStringContainsString( 'Advanced Custom Fields', $label );
	}

	public function test_get_notice_items_includes_elementor_when_missing() : void {
		$items = CLEFA_Plugin_Dependencies::get_notice_items();
		$this->assertNotEmpty( $items );
		$levels = array_column( $items, 'level' );
		$this->assertContains( 'warning', $levels );
	}

	public function test_is_available_for_elementor() : void {
		$this->assertFalse( CLEFA_Plugin_Dependencies::is_available( CLEFA_Plugin_Dependencies::DEP_ELEMENTOR ) );
	}

	public function test_meets_requirements_elementor() : void {
		$this->assertFalse( CLEFA_Plugin_Dependencies::meets_requirements( array( CLEFA_Plugin_Dependencies::DEP_ELEMENTOR ) ) );
	}

	public function test_get_missing_message() : void {
		$msg = CLEFA_Plugin_Dependencies::get_missing_message( CLEFA_Plugin_Dependencies::DEP_WOOCOMMERCE );
		$this->assertStringContainsString( 'WooCommerce', $msg );
	}
}
