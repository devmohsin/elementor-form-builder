<?php
/**
 * Tests for CLEFA_Form_Submission_Handler
 *
 * Private methods are exercised via ReflectionClass to keep tests
 * focused on logic rather than full HTTP request mocking.
 *
 * Public handle() tests use the CLEFA_Tables mock (defined in bootstrap)
 * and a simple WP_REST_Request stub to drive the guard clauses.
 */

class FormSubmissionHandlerTest extends \PHPUnit\Framework\TestCase {

	private CLEFA_Form_Submission_Handler $handler;
	private ReflectionClass $ref;

	protected function setUp(): void {
		$this->handler = new CLEFA_Form_Submission_Handler();
		$this->ref     = new ReflectionClass( $this->handler );

		CLEFA_Tables::$mock_form     = null;
		CLEFA_Settings_Page::$overrides = array();
		CLEFA_Audit_Log::$last_event    = null;
		CLEFA_Audit_Log::$last_context  = null;

		global $clefa_test_current_user_id, $clefa_test_user_caps, $clefa_test_transients;
		$clefa_test_current_user_id = 0;
		$clefa_test_user_caps       = array();
		$clefa_test_transients      = array();
	}

	protected function tearDown(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps, $clefa_test_transients;
		$clefa_test_current_user_id = 0;
		$clefa_test_user_caps       = array();
		$clefa_test_transients      = array();
		CLEFA_Tables::$mock_form    = null;
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/** Call a private/protected method via reflection. */
	private function invoke( string $method, ...$args ) {
		$m = $this->ref->getMethod( $method );
		$m->setAccessible( true );
		return $m->invoke( $this->handler, ...$args );
	}

	private function make_request( array $params ): WP_REST_Request {
		return new WP_REST_Request( $params );
	}

	private function make_form( array $overrides = array() ): array {
		return array_merge( array(
			'id'        => 1,
			'form_uuid' => 'test-uuid-001',
			'form_name' => 'Test Form',
			'status'    => 'published',
			'config'    => array(
				'form_name' => 'Test Form',
				'steps'     => array(
					array(
						'step_id' => 's1',
						'fields'  => array(
							array(
								'field_id'   => 'name',
								'field_type' => 'text',
								'label'      => 'Name',
								'required'   => false,
							),
						),
					),
				),
				'settings' => array(),
				'actions'  => array(),
				'notifications' => array(),
			),
		), $overrides );
	}

	// -----------------------------------------------------------------------
	// apply_required_overrides (private method via reflection)
	// -----------------------------------------------------------------------

	public function test_apply_required_overrides_sets_required_true(): void {
		$config = array(
			'steps' => array(
				array(
					'fields' => array(
						array( 'field_id' => 'email', 'required' => false ),
					),
				),
			),
		);

		$result = $this->invoke( 'apply_required_overrides', $config, array( 'email' => true ) );

		$this->assertTrue( $result['steps'][0]['fields'][0]['required'] );
	}

	public function test_apply_required_overrides_sets_required_false(): void {
		$config = array(
			'steps' => array(
				array(
					'fields' => array(
						array( 'field_id' => 'phone', 'required' => true ),
					),
				),
			),
		);

		$result = $this->invoke( 'apply_required_overrides', $config, array( 'phone' => false ) );

		$this->assertFalse( $result['steps'][0]['fields'][0]['required'] );
	}

	public function test_apply_required_overrides_does_not_touch_unmentioned_fields(): void {
		$config = array(
			'steps' => array(
				array(
					'fields' => array(
						array( 'field_id' => 'name',  'required' => true  ),
						array( 'field_id' => 'email', 'required' => false ),
					),
				),
			),
		);

		$result = $this->invoke( 'apply_required_overrides', $config, array( 'email' => true ) );

		$this->assertTrue(  $result['steps'][0]['fields'][0]['required'] ); // name unchanged
		$this->assertTrue(  $result['steps'][0]['fields'][1]['required'] ); // email overridden
	}

	public function test_apply_required_overrides_handles_multiple_steps(): void {
		$config = array(
			'steps' => array(
				array( 'fields' => array( array( 'field_id' => 'a', 'required' => false ) ) ),
				array( 'fields' => array( array( 'field_id' => 'b', 'required' => false ) ) ),
			),
		);

		$result = $this->invoke( 'apply_required_overrides', $config, array( 'a' => true, 'b' => true ) );

		$this->assertTrue( $result['steps'][0]['fields'][0]['required'] );
		$this->assertTrue( $result['steps'][1]['fields'][0]['required'] );
	}

	public function test_apply_required_overrides_empty_overrides_is_noop(): void {
		$config = array(
			'steps' => array(
				array( 'fields' => array( array( 'field_id' => 'x', 'required' => true ) ) ),
			),
		);

		$result = $this->invoke( 'apply_required_overrides', $config, array() );

		$this->assertTrue( $result['steps'][0]['fields'][0]['required'] );
	}

	// -----------------------------------------------------------------------
	// verify_time_token (private method via reflection)
	// -----------------------------------------------------------------------

	private function make_time_token( int $timestamp ): string {
		$sig = hash_hmac( 'sha256', (string) $timestamp, wp_salt( 'nonce' ) );
		return base64_encode( $timestamp . ':' . $sig );
	}

	public function test_verify_time_token_returns_false_for_invalid_base64(): void {
		$result = $this->invoke( 'verify_time_token', 'not-valid-base64!!!!!', 1 );
		$this->assertFalse( $result );
	}

	public function test_verify_time_token_returns_false_for_tampered_signature(): void {
		$token = base64_encode( time() . ':tampered_sig' );
		$result = $this->invoke( 'verify_time_token', $token, 1 );
		$this->assertFalse( $result );
	}

	public function test_verify_time_token_returns_false_when_not_enough_time_elapsed(): void {
		// Token created right now — but we require 60 seconds elapsed.
		$token  = $this->make_time_token( time() );
		$result = $this->invoke( 'verify_time_token', $token, 60 );
		$this->assertFalse( $result );
	}

	public function test_verify_time_token_returns_true_when_enough_time_elapsed(): void {
		// Token created 10 seconds ago — require 3 seconds.
		$token  = $this->make_time_token( time() - 10 );
		$result = $this->invoke( 'verify_time_token', $token, 3 );
		$this->assertTrue( $result );
	}

	public function test_verify_time_token_returns_false_for_empty_string(): void {
		$result = $this->invoke( 'verify_time_token', '', 1 );
		$this->assertFalse( $result );
	}

	// -----------------------------------------------------------------------
	// handle() — guard clauses (no form_id, form not found, inactive)
	// -----------------------------------------------------------------------

	public function test_handle_returns_wp_error_when_form_id_missing(): void {
		$request = $this->make_request( array( 'form_id' => 0 ) );
		$result  = $this->handler->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_handle_returns_wp_error_when_form_not_found(): void {
		CLEFA_Tables::$mock_form = null;

		$request = $this->make_request( array( 'form_id' => 99 ) );
		$result  = $this->handler->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_handle_returns_wp_error_when_form_is_draft(): void {
		CLEFA_Tables::$mock_form = $this->make_form( array( 'status' => 'draft' ) );

		$request = $this->make_request( array( 'form_id' => 1 ) );
		$result  = $this->handler->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_handle_accepts_published_status(): void {
		CLEFA_Tables::$mock_form = $this->make_form( array( 'status' => 'published' ) );
		CLEFA_Settings_Page::$overrides['enable_submission_storage'] = false;

		$request = $this->make_request( array(
			'form_id' => 1,
			'data'    => array(),
		) );
		$result = $this->handler->handle( $request );

		$this->assertNotInstanceOf( WP_Error::class, $result );
	}

	public function test_handle_accepts_active_status(): void {
		CLEFA_Tables::$mock_form = $this->make_form( array( 'status' => 'active' ) );
		CLEFA_Settings_Page::$overrides['enable_submission_storage'] = false;

		$request = $this->make_request( array(
			'form_id' => 1,
			'data'    => array(),
		) );
		$result = $this->handler->handle( $request );

		$this->assertNotInstanceOf( WP_Error::class, $result );
	}

	// -----------------------------------------------------------------------
	// handle() — login requirement
	// -----------------------------------------------------------------------

	public function test_handle_returns_wp_error_when_login_required_and_guest(): void {
		CLEFA_Tables::$mock_form = $this->make_form( array(
			'config' => array(
				'steps'    => array( array( 'step_id' => 's1', 'fields' => array() ) ),
				'settings' => array( 'require_login' => true ),
				'actions'  => array(),
				'notifications' => array(),
			),
		) );

		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 0; // guest

		$request = $this->make_request( array( 'form_id' => 1, 'data' => array() ) );
		$result  = $this->handler->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	// -----------------------------------------------------------------------
	// handle() — validation errors returned as WP_Error
	// -----------------------------------------------------------------------

	public function test_handle_returns_wp_error_on_validation_failure(): void {
		CLEFA_Tables::$mock_form = $this->make_form( array(
			'config' => array(
				'steps' => array(
					array(
						'step_id' => 's1',
						'fields'  => array(
							array(
								'field_id'         => 'email',
								'field_type'       => 'email',
								'label'            => 'Email',
								'required'         => true,
								'validation_rules' => array(
									array( 'rule' => 'required', 'value' => '', 'message' => '' ),
								),
							),
						),
					),
				),
				'settings'      => array(),
				'actions'       => array(),
				'notifications' => array(),
			),
		) );
		CLEFA_Settings_Page::$overrides['enable_submission_storage'] = false;

		$request = $this->make_request( array(
			'form_id' => 1,
			'data'    => array( 'email' => '' ), // missing required value
		) );

		$result = $this->handler->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	// -----------------------------------------------------------------------
	// handle() — successful submission response shape
	// -----------------------------------------------------------------------

	public function test_handle_returns_success_true_on_valid_submission(): void {
		CLEFA_Tables::$mock_form = $this->make_form();
		CLEFA_Settings_Page::$overrides['enable_submission_storage'] = false;

		$request = $this->make_request( array(
			'form_id' => 1,
			'data'    => array( 'name' => 'Alice' ),
		) );

		$result = $this->handler->handle( $request );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
	}

	public function test_handle_response_includes_expected_keys(): void {
		CLEFA_Tables::$mock_form = $this->make_form();
		CLEFA_Settings_Page::$overrides['enable_submission_storage'] = false;

		$result = $this->handler->handle(
			$this->make_request( array( 'form_id' => 1, 'data' => array() ) )
		);

		$this->assertArrayHasKey( 'success',        $result );
		$this->assertArrayHasKey( 'message',        $result );
		$this->assertArrayHasKey( 'redirect_url',   $result );
		$this->assertArrayHasKey( 'action_results', $result );
		$this->assertArrayHasKey( 'event_payload',  $result );
	}

	public function test_handle_writes_audit_log_on_success(): void {
		CLEFA_Tables::$mock_form = $this->make_form();
		CLEFA_Settings_Page::$overrides['enable_submission_storage'] = false;

		$this->handler->handle(
			$this->make_request( array( 'form_id' => 1, 'data' => array() ) )
		);

		$this->assertSame( 'form_submitted', CLEFA_Audit_Log::$last_event );
		$this->assertSame( 1, CLEFA_Audit_Log::$last_context['form_id'] );
	}

	// -----------------------------------------------------------------------
	// handle() — honeypot returns success silently
	// -----------------------------------------------------------------------

	public function test_handle_returns_silent_success_when_honeypot_filled(): void {
		$form = $this->make_form();
		$form['config']['settings']['enable_antispam'] = true;
		CLEFA_Tables::$mock_form = $form;

		$hp_key = 'clefa_hp_test-uuid-001';

		$request = $this->make_request( array(
			'form_id'  => 1,
			'data'     => array(),
			$hp_key    => 'spam_bot_value',
		) );

		$result = $this->handler->handle( $request );

		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertTrue( $result['success'] );
	}

	// -----------------------------------------------------------------------
	// handle() — duplicate submission block
	// -----------------------------------------------------------------------

	public function test_handle_returns_wp_error_on_duplicate_submission(): void {
		$form = $this->make_form();
		$form['config']['settings']['block_repeated_submission'] = true;
		CLEFA_Tables::$mock_form = $form;

		global $clefa_test_transients;
		// Simulate a transient already set (previous submission from same IP).
		// We can't easily control the IP, but we can set the transient key directly.
		// The handler builds key as md5(ip . '_' . form_id).
		$ip  = '';       // bootstrap returns '' for missing SERVER vars
		$key = 'clefa_dedup_' . md5( $ip . '_' . 1 );
		$clefa_test_transients[ $key ] = 1;

		$request = $this->make_request( array( 'form_id' => 1, 'data' => array() ) );
		$result  = $this->handler->handle( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
	}
}
