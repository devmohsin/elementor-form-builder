<?php

use PHPUnit\Framework\TestCase;

class ConfigNormalizerTest extends TestCase {

	private function normalizer() : CLEFA_Config_Normalizer {
		return new CLEFA_Config_Normalizer();
	}

	public function test_normalize_strips_duplicate_field_ids() : void {
		$config = array(
			'form_name' => '  Test Form  ',
			'steps'     => array(
				array(
					'step_id' => 'step_1',
					'fields'  => array(
						array( 'field_id' => 'email', 'field_type' => 'email', 'label' => 'Email' ),
						array( 'field_id' => 'email', 'field_type' => 'text', 'label' => 'Dup' ),
						array( 'field_id' => 'name', 'field_type' => 'text' ),
					),
				),
			),
		);

		$result = $this->normalizer()->normalize( $config );
		$this->assertSame( 'Test Form', $result['form_name'] );
		$this->assertCount( 2, $result['steps'][0]['fields'] );
		$this->assertSame( 'email', $result['steps'][0]['fields'][0]['field_id'] );
	}

	public function test_normalize_skips_steps_without_id() : void {
		$config = array(
			'steps' => array(
				array( 'step_id' => '', 'fields' => array() ),
				array( 'step_id' => 'valid', 'fields' => array() ),
			),
		);
		$result = $this->normalizer()->normalize( $config );
		$this->assertCount( 1, $result['steps'] );
		$this->assertSame( 'valid', $result['steps'][0]['step_id'] );
	}

	public function test_normalize_settings_booleans() : void {
		$config = array(
			'settings' => array(
				'require_login'     => 1,
				'enable_draft'      => 0,
				'redirect_url'      => 'https://example.com/thanks',
				'allowed_roles'     => array( 'administrator', 'editor' ),
			),
		);
		$result = $this->normalizer()->normalize( $config );
		$this->assertTrue( $result['settings']['require_login'] );
		$this->assertFalse( $result['settings']['enable_draft'] );
		$this->assertSame( 'https://example.com/thanks', $result['settings']['redirect_url'] );
		$this->assertSame( array( 'administrator', 'editor' ), $result['settings']['allowed_roles'] );
	}

	public function test_normalize_actions_filters_invalid_types() : void {
		$config = array(
			'actions' => array(
				array( 'action_type' => 'redirect', 'order' => 2, 'enabled' => true ),
				array( 'action_type' => 'not_a_real_action', 'order' => 1 ),
				array( 'action_type' => 'save_submission', 'order' => 0 ),
			),
		);
		$result = $this->normalizer()->normalize( $config );
		$this->assertCount( 2, $result['actions'] );
		$this->assertSame( 'save_submission', $result['actions'][0]['action_type'] );
		$this->assertSame( 'redirect', $result['actions'][1]['action_type'] );
	}

	public function test_normalize_notifications_sanitizes_message() : void {
		$config = array(
			'notifications' => array(
				array(
					'notification_id' => 'n1',
					'subject'         => 'Hello',
					'message'         => '<p>Hi</p><script>bad</script>',
				),
			),
		);
		$result = $this->normalizer()->normalize( $config );
		$this->assertStringContainsString( '<p>Hi</p>', $result['notifications'][0]['message'] );
		$this->assertStringNotContainsString( '<script>', $result['notifications'][0]['message'] );
	}

	public function test_generate_feature_map_detects_capabilities() : void {
		$normalized = array(
			'steps' => array(
				array(
					'step_id' => 's1',
					'routing' => array( array( 'target_step' => 's2' ) ),
					'fields'  => array(
						array( 'field_id' => 'f1', 'field_type' => 'file' ),
						array( 'field_id' => 'f2', 'field_type' => 'repeater' ),
						array( 'field_id' => 'f3', 'field_type' => 'select2', 'live_check' => array( 'enabled' => true ) ),
					),
				),
				array( 'step_id' => 's2', 'fields' => array() ),
			),
		);
		$map = $this->normalizer()->generate_feature_map( $normalized );
		$this->assertTrue( $map['has_steps'] );
		$this->assertTrue( $map['has_uploads'] );
		$this->assertTrue( $map['has_repeater'] );
		$this->assertTrue( $map['has_select2'] );
		$this->assertTrue( $map['has_routing'] );
		$this->assertTrue( $map['has_live_checks'] );
		$this->assertSame( 3, $map['field_count'] );
		$this->assertSame( 2, $map['step_count'] );
	}
}
