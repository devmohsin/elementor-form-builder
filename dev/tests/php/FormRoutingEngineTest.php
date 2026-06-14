<?php

use PHPUnit\Framework\TestCase;

class FormRoutingEngineTest extends TestCase {

	private function config() : array {
		return array(
			'steps' => array(
				array(
					'step_id' => 'step_start',
					'fields'  => array( array( 'field_id' => 'path' ) ),
					'routing' => array(
						array( 'source_field' => 'path', 'operator' => 'equals', 'compare_value' => 'a', 'target_step' => 'step_a' ),
						array( 'source_field' => 'path', 'operator' => 'equals', 'compare_value' => 'b', 'target_step' => 'step_b' ),
					),
				),
				array( 'step_id' => 'step_a', 'fields' => array( array( 'field_id' => 'field_a' ) ), 'routing' => array() ),
				array( 'step_id' => 'step_b', 'fields' => array( array( 'field_id' => 'field_b' ) ), 'routing' => array() ),
			),
		);
	}

	public function test_routes_to_step_a() : void {
		$active = CLEFA_Form_Routing_Engine::compute_active_steps( $this->config(), array( 'path' => 'a' ) );
		$this->assertSame( array( 'step_start', 'step_a' ), $active );
	}

	public function test_routes_to_step_b() : void {
		$active = CLEFA_Form_Routing_Engine::compute_active_steps( $this->config(), array( 'path' => 'b' ) );
		$this->assertSame( array( 'step_start', 'step_b' ), $active );
	}

	public function test_default_sequential_when_no_rule_matches() : void {
		$active = CLEFA_Form_Routing_Engine::compute_active_steps( $this->config(), array( 'path' => 'unknown' ) );
		$this->assertContains( 'step_start', $active );
		$this->assertContains( 'step_a', $active );
	}

	public function test_get_active_field_ids() : void {
		$active = array( 'step_start', 'step_a' );
		$ids    = CLEFA_Form_Routing_Engine::get_active_field_ids( $this->config(), $active );
		$this->assertContains( 'path', $ids );
		$this->assertContains( 'field_a', $ids );
		$this->assertNotContains( 'field_b', $ids );
	}

	public function test_empty_config_returns_empty() : void {
		$this->assertSame( array(), CLEFA_Form_Routing_Engine::compute_active_steps( array(), array() ) );
	}
}
