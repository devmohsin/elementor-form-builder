<?php
/**
 * End-to-end tests for every layer inside CLEFA_Form_Submission_Handler::handle().
 *
 * Guards, antispam, rate limits, routing, validation, submission storage,
 * actions, notifications, redirect, audit log, and response shape.
 */

class SubmissionMiddlewareTest extends PHPUnit\Framework\TestCase {

	private CLEFA_Form_Submission_Handler $handler;

	protected function setUp(): void {
		clefa_test_reset_action_stores();
		wp_set_current_user( 0 );
		CLEFA_Tables::$mock_form        = null;
		CLEFA_Settings_Page::$overrides = array();
		CLEFA_Audit_Log::$last_event    = null;
		CLEFA_Audit_Log::$last_context  = null;
		$this->handler = new CLEFA_Form_Submission_Handler();
	}

	private function submit( array $params, array $form_overrides = array(), array $settings_overrides = array() ): array {
		CLEFA_Settings_Page::$overrides = array_merge(
			array(
				'enable_submission_storage' => true,
				'enable_rate_limiting'      => false,
			),
			$settings_overrides
		);

		if ( ! empty( $form_overrides ) ) {
			CLEFA_Tables::$mock_form = array_replace_recursive( $this->base_form(), $form_overrides );
		} elseif ( null === CLEFA_Tables::$mock_form ) {
			CLEFA_Tables::$mock_form = $this->base_form();
		}

		$params = array_merge(
			array(
				'form_id'     => 1,
				'instance_id' => 'middleware-test',
				'data'        => array(),
			),
			$params
		);

		$response = $this->handler->handle( new WP_REST_Request( $params ) );
		return is_array( $response ) ? $response : array( '_wp_error' => $response );
	}

	private function base_form(): array {
		return array(
			'id'        => 1,
			'form_uuid' => 'middleware-uuid',
			'form_name' => 'Middleware Form',
			'status'    => 'published',
			'config'    => array(
				'form_name'     => 'Middleware Form',
				'settings'      => array(
					'enable_antispam'           => false,
					'block_repeated_submission' => false,
				),
				'notifications' => array(),
				'actions'       => array(
					CLEFA_Programmatic_Form_Builder::action( 'save_submission' ),
				),
				'steps'         => array(
					array(
						'step_id' => 'step_1',
						'fields'  => array(
							CLEFA_Programmatic_Form_Builder::field( 'name', 'text', array( 'required' => false ) ),
						),
					),
				),
			),
		);
	}

	private function make_time_token( int $timestamp ): string {
		$sig = hash_hmac( 'sha256', (string) $timestamp, wp_salt( 'nonce' ) );
		return base64_encode( $timestamp . ':' . $sig );
	}

	// ------------------------------------------------------------------
	// REST / guard clauses
	// ------------------------------------------------------------------

	public function test_handle_rejects_missing_form_id(): void {
		$result = $this->submit( array( 'form_id' => 0 ) );
		$this->assertArrayHasKey( '_wp_error', $result );
		$this->assertSame( 'clefa_missing_form_id', $result['_wp_error']->get_error_code() );
	}

	public function test_handle_rejects_missing_form_record(): void {
		CLEFA_Tables::$mock_form = null;
		$result = $this->handler->handle( new WP_REST_Request( array( 'form_id' => 1, 'data' => array() ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'clefa_form_not_found', $result->get_error_code() );
	}

	public function test_handle_rejects_inactive_form(): void {
		$result = $this->submit( array(), array( 'status' => 'draft' ) );
		$this->assertArrayHasKey( '_wp_error', $result );
		$this->assertSame( 'clefa_form_inactive', $result['_wp_error']->get_error_code() );
	}

	public function test_handle_requires_login_when_configured(): void {
		$form = $this->base_form();
		$form['config']['settings']['require_login'] = true;
		CLEFA_Tables::$mock_form = $form;
		$result = $this->handler->handle( new WP_REST_Request( array( 'form_id' => 1, 'data' => array() ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'clefa_login_required', $result->get_error_code() );
	}

	public function test_handle_enforces_allowed_roles(): void {
		global $clefa_test_users;
		wp_insert_user( array(
			'user_login' => 'editor_user',
			'user_email' => 'editor@example.com',
			'role'       => 'editor',
		) );
		wp_set_current_user( 1 );

		$form = $this->base_form();
		$form['config']['settings']['allowed_roles'] = array( 'subscriber' );
		CLEFA_Tables::$mock_form = $form;

		$result = $this->handler->handle( new WP_REST_Request( array( 'form_id' => 1, 'data' => array() ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'clefa_permission_denied', $result->get_error_code() );
	}

	// ------------------------------------------------------------------
	// Antispam (silent success — no validation/actions/storage)
	// ------------------------------------------------------------------

	public function test_handle_honeypot_returns_silent_success_without_side_effects(): void {
		global $clefa_test_submissions;
		$form = $this->base_form();
		$form['config']['settings']['enable_antispam'] = true;
		$form['config']['actions'] = array(
			CLEFA_Programmatic_Form_Builder::action( 'create_post', array(
				'post_title_field' => 'name',
				'post_status'      => 'publish',
			) ),
		);
		CLEFA_Tables::$mock_form = $form;

		$result = $this->handler->handle( new WP_REST_Request( array(
			'form_id'              => 1,
			'data'                 => array( 'name' => 'Spam Title' ),
			'clefa_hp_middleware-uuid' => 'bot',
		) ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEmpty( $clefa_test_submissions );
		$this->assertNull( CLEFA_Audit_Log::$last_event );
	}

	public function test_handle_fast_submit_token_returns_silent_success(): void {
		global $clefa_test_submissions;
		$form = $this->base_form();
		$form['config']['settings']['enable_antispam'] = true;
		$form['config']['settings']['min_submit_seconds'] = 60;
		CLEFA_Tables::$mock_form = $form;

		$result = $this->handler->handle( new WP_REST_Request( array(
			'form_id'   => 1,
			'data'      => array(),
			'_clefa_ts' => $this->make_time_token( time() ),
		) ) );

		$this->assertTrue( $result['success'] );
		$this->assertEmpty( $clefa_test_submissions );
	}

	public function test_handle_low_interaction_count_returns_silent_success(): void {
		global $clefa_test_submissions;
		$form = $this->base_form();
		$form['config']['settings']['enable_antispam'] = true;
		$form['config']['settings']['min_interactions'] = 5;
		CLEFA_Tables::$mock_form = $form;

		$result = $this->handler->handle( new WP_REST_Request( array(
			'form_id'   => 1,
			'data'      => array(),
			'_clefa_ic' => 1,
		) ) );

		$this->assertTrue( $result['success'] );
		$this->assertEmpty( $clefa_test_submissions );
	}

	public function test_handle_missing_user_agent_returns_silent_success(): void {
		global $clefa_test_submissions;
		$form = $this->base_form();
		$form['config']['settings']['enable_antispam']      = true;
		$form['config']['settings']['require_user_agent']   = true;
		CLEFA_Tables::$mock_form = $form;

		$prev_ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
		unset( $_SERVER['HTTP_USER_AGENT'] );

		$result = $this->handler->handle( new WP_REST_Request( array(
			'form_id' => 1,
			'data'    => array(),
		) ) );

		if ( null !== $prev_ua ) {
			$_SERVER['HTTP_USER_AGENT'] = $prev_ua;
		}

		$this->assertTrue( $result['success'] );
		$this->assertEmpty( $clefa_test_submissions );
	}

	// ------------------------------------------------------------------
	// Duplicate block + rate limiting
	// ------------------------------------------------------------------

	public function test_handle_blocks_duplicate_submission(): void {
		global $clefa_test_transients;
		$form = $this->base_form();
		$form['config']['settings']['block_repeated_submission'] = true;
		CLEFA_Tables::$mock_form = $form;

		$key = 'clefa_dedup_' . md5( '_' . 1 );
		$clefa_test_transients[ $key ] = 1;

		$result = $this->handler->handle( new WP_REST_Request( array( 'form_id' => 1, 'data' => array() ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'clefa_duplicate_submission', $result->get_error_code() );
	}

	public function test_handle_rate_limit_blocks_excess_submissions(): void {
		global $clefa_test_transients;
		$key = 'clefa_rl_' . md5( '_' . 1 );
		$clefa_test_transients[ $key ] = 5;

		$result = $this->submit( array(), array(), array(
			'enable_rate_limiting' => true,
			'rate_limit_max'       => 5,
			'rate_limit_window'    => 1,
		) );

		$this->assertArrayHasKey( '_wp_error', $result );
		$this->assertSame( 'clefa_rate_limited', $result['_wp_error']->get_error_code() );
	}

	// ------------------------------------------------------------------
	// Routing intersection
	// ------------------------------------------------------------------

	public function test_handle_skips_fields_on_unvisited_routed_steps(): void {
		$form = $this->base_form();
		$form['config']['steps'] = array(
			array(
				'step_id' => 'step_1',
				'routing' => array(
					array(
						'source_field'  => 'user_type',
						'operator'      => 'equals',
						'compare_value' => 'creator',
						'target_step'   => 'step_3',
					),
				),
				'fields'  => array(
					CLEFA_Programmatic_Form_Builder::field( 'user_type', 'text' ),
				),
			),
			array(
				'step_id' => 'step_2',
				'fields'  => array(
					CLEFA_Programmatic_Form_Builder::field( 'secret', 'text', array( 'required' => true ) ),
				),
			),
			array(
				'step_id' => 'step_3',
				'fields'  => array(
					CLEFA_Programmatic_Form_Builder::field( 'title', 'text', array( 'required' => true ) ),
				),
			),
		);
		CLEFA_Tables::$mock_form = $form;

		$pass = $this->handler->handle( new WP_REST_Request( array(
			'form_id' => 1,
			'data'    => array(
				'user_type' => 'creator',
				'title'     => 'Routed Title',
			),
		) ) );
		$this->assertIsArray( $pass );
		$this->assertTrue( $pass['success'] );

		$fail = $this->handler->handle( new WP_REST_Request( array(
			'form_id' => 1,
			'data'    => array(
				'user_type' => 'customer',
				'title'     => 'Needs Secret',
			),
		) ) );
		$this->assertInstanceOf( WP_Error::class, $fail );
		$this->assertSame( 'clefa_validation_failed', $fail->get_error_code() );
		$this->assertArrayHasKey( 'secret', $fail->get_error_data()['errors'] ?? array() );
	}

	// ------------------------------------------------------------------
	// Validation, storage, actions, response
	// ------------------------------------------------------------------

	public function test_handle_validation_failure_returns_field_errors(): void {
		$form = $this->base_form();
		$form['config']['steps'][0]['fields'] = array(
			CLEFA_Programmatic_Form_Builder::field( 'email', 'email', array( 'required' => true ) ),
		);
		CLEFA_Tables::$mock_form = $form;

		$result = $this->handler->handle( new WP_REST_Request( array(
			'form_id' => 1,
			'data'    => array( 'email' => '' ),
		) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 422, $result->get_error_data()['status'] ?? 0 );
		$this->assertArrayHasKey( 'email', $result->get_error_data()['errors'] ?? array() );
	}

	public function test_handle_persists_submission_row(): void {
		global $clefa_test_submissions;
		$result = $this->submit( array( 'data' => array( 'name' => 'Stored Name' ) ) );
		$this->assertTrue( $result['success'] );
		$this->assertCount( 1, $clefa_test_submissions );
		$this->assertSame( 1, (int) $result['submission_id'] );
		$row = reset( $clefa_test_submissions );
		$this->assertStringContainsString( 'Stored Name', $row['submitted_data_json'] ?? '' );
	}

	public function test_handle_stores_action_results_on_submission_row(): void {
		global $clefa_test_submissions;
		$this->submit(
			array( 'data' => array( 'name' => 'Action Result Post' ) ),
			array(
				'config' => array(
					'actions' => array(
						CLEFA_Programmatic_Form_Builder::action( 'create_post', array(
							'post_title_field' => 'name',
							'post_status'      => 'publish',
						) ),
					),
				),
			)
		);
		$row = reset( $clefa_test_submissions );
		$this->assertStringContainsString( 'create_post', $row['action_results_json'] ?? '' );
	}

	public function test_handle_writes_audit_log_and_event_payload(): void {
		$result = $this->submit( array( 'data' => array( 'name' => 'Audit Test' ) ) );
		$this->assertSame( 'form_submitted', CLEFA_Audit_Log::$last_event );
		$this->assertSame( 1, CLEFA_Audit_Log::$last_context['form_id'] );
		$this->assertArrayHasKey( 'event_payload', $result );
		$this->assertSame( 1, $result['event_payload']['form_id'] );
	}

	public function test_handle_uses_settings_redirect_url(): void {
		$result = $this->submit(
			array(),
			array(
				'config' => array(
					'settings' => array(
						'redirect_url' => 'https://example.com/thanks',
					),
				),
			)
		);
		$this->assertSame( 'https://example.com/thanks', $result['redirect_url'] );
	}

	public function test_handle_action_redirect_overrides_settings_redirect(): void {
		$result = $this->submit(
			array(),
			array(
				'config' => array(
					'settings' => array(
						'redirect_url' => 'https://example.com/settings',
					),
					'actions'  => array(
						CLEFA_Programmatic_Form_Builder::action( 'redirect', array(
							'redirect_url' => 'https://example.com/action',
						) ),
					),
				),
			)
		);
		$this->assertSame( 'https://example.com/action', $result['redirect_url'] );
	}

	// ------------------------------------------------------------------
	// Notifications (via clefa_after_submission_save hook)
	// ------------------------------------------------------------------

	public function test_handle_sends_enabled_notification_email(): void {
		global $clefa_test_mails;
		$this->submit(
			array( 'data' => array( 'email' => 'lead@example.com' ) ),
			array(
				'config' => array(
					'steps'         => array(
						array(
							'step_id' => 'step_1',
							'fields'  => array(
								CLEFA_Programmatic_Form_Builder::field( 'email', 'email' ),
							),
						),
					),
					'notifications' => array(
						array(
							'enabled'        => true,
							'recipient_type' => 'admin',
							'subject'        => 'New lead from {field:email}',
							'body'           => 'Email: {field:email}',
						),
					),
				),
			)
		);

		$this->assertNotEmpty( $clefa_test_mails );
		$this->assertSame( 'admin@example.com', $clefa_test_mails[0]['to'] );
		$this->assertStringContainsString( 'lead@example.com', $clefa_test_mails[0]['message'] );

		$notification_audit = array_filter(
			(array) ( $GLOBALS['clefa_test_audit_events'] ?? array() ),
			static fn( $row ) => ( $row['event'] ?? '' ) === 'notification_sent'
		);
		$this->assertNotEmpty( $notification_audit );
	}

	// ------------------------------------------------------------------
	// Full integrated flow
	// ------------------------------------------------------------------

	public function test_handle_full_flow_create_post_with_notification_and_redirect(): void {
		global $clefa_test_posts, $clefa_test_submissions, $clefa_test_mails;
		$result = $this->submit(
			array(
				'data' => array(
					'title' => 'Full Flow Post',
					'email' => 'author@example.com',
				),
			),
			array(
				'config' => array(
					'settings'      => array(
						'redirect_url' => 'https://example.com/done',
					),
					'steps'         => array(
						array(
							'step_id' => 'step_1',
							'fields'  => array(
								CLEFA_Programmatic_Form_Builder::field( 'title', 'text', array( 'required' => true ) ),
								CLEFA_Programmatic_Form_Builder::field( 'email', 'email', array( 'required' => true ) ),
							),
						),
					),
					'actions'       => array(
						CLEFA_Programmatic_Form_Builder::action( 'create_post', array(
							'post_title_field' => 'title',
							'post_status'      => 'publish',
						) ),
					),
					'notifications' => array(
						array(
							'enabled'        => true,
							'recipient_type' => 'custom',
							'custom_emails'  => 'ops@example.com',
							'subject'        => 'Post created: {field:title}',
							'body'           => '{all_fields}',
						),
					),
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertCount( 1, $clefa_test_submissions );
		$this->assertNotEmpty( $clefa_test_posts );
		$this->assertNotEmpty( $clefa_test_mails );
		$this->assertSame( 'https://example.com/done', $result['redirect_url'] );
		$this->assertSame( 'form_submitted', CLEFA_Audit_Log::$last_event );
	}
}
