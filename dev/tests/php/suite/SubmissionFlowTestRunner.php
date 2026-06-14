<?php
/**
 * Runs form scenarios through CLEFA_Form_Submission_Handler::handle() — full production path.
 */

class CLEFA_Submission_Flow_Test_Runner extends CLEFA_Programmatic_Form_Test_Runner {

	private CLEFA_Form_Submission_Handler $handler;

	public function __construct() {
		$this->handler = new CLEFA_Form_Submission_Handler();
	}

	/**
	 * @param array $form Catalog form entry (id, label, config, cases).
	 */
	public function run_form( array $form, array $case ): array {
		$config = $this->prepare_config( $form['config'] ?? array(), $case );

		CLEFA_Tables::$mock_form = array(
			'id'        => 1,
			'form_uuid' => sanitize_key( $form['id'] ?? 'suite-form' ),
			'form_name' => $form['label'] ?? 'Suite Form',
			'status'    => $case['form_status'] ?? 'published',
			'config'    => $config,
		);

		$user_id = (int) ( $case['user_id'] ?? 0 );
		if ( array_key_exists( 'user_id', $case ) ) {
			wp_set_current_user( $user_id );
		} elseif ( empty( $case['seed'] ) ) {
			wp_set_current_user( 1 );
		}

		$this->apply_settings_overrides( $case );

		$before = $this->snapshot_stores();
		global $clefa_test_submissions, $clefa_test_audit_events;
		$submissions_before = count( $clefa_test_submissions ?? array() );
		$audit_before       = count( $clefa_test_audit_events ?? array() );

		$params = array_merge(
			array(
				'form_id'     => 1,
				'instance_id' => $case['instance_id'] ?? 'test-instance',
				'data'        => $case['data'] ?? array(),
			),
			(array) ( $case['request_params'] ?? array() )
		);

		$response = $this->handler->handle( new WP_REST_Request( $params ) );

		$result = $this->map_handle_response( $response );
		$result['submissions_before'] = $submissions_before;
		$result['submissions_after']  = count( $clefa_test_submissions ?? array() );
		$result['audit_events_before']  = $audit_before;
		$result['audit_events_after']   = count( $clefa_test_audit_events ?? array() );
		$result['handle_response']      = $response;

		$result['verify'] = $this->run_verifications(
			$case['verify'] ?? array(),
			$result,
			$before,
			$this->snapshot_stores()
		);

		return $result;
	}

	public function assert_case_expectations( array $case, array $result, PHPUnit\Framework\TestCase $test ): void {
		parent::assert_case_expectations( $case, $result, $test );

		$expect_pass = ! empty( $case['expect_pass'] );
		$handle_opts = (array) ( $case['handle'] ?? array() );

		if ( $expect_pass ) {
			$test->assertTrue( $result['handle_success'], 'Expected handle() success response.' );
			$test->assertIsArray( $result['handle_response'] );
			$test->assertArrayHasKey( 'success', $result['handle_response'] );
			$test->assertArrayHasKey( 'message', $result['handle_response'] );
			$test->assertArrayHasKey( 'redirect_url', $result['handle_response'] );
			$test->assertArrayHasKey( 'action_results', $result['handle_response'] );
			$test->assertArrayHasKey( 'event_payload', $result['handle_response'] );

			if ( empty( $handle_opts['skip_submission_assert'] ) ) {
				$test->assertGreaterThan(
					$result['submissions_before'],
					$result['submissions_after'],
					'Expected submission row to be saved through handle().'
				);
			}

			if ( empty( $handle_opts['skip_audit_assert'] ) ) {
				$test->assertSame( 'form_submitted', CLEFA_Audit_Log::$last_event );
				$test->assertGreaterThan(
					$result['audit_events_before'],
					$result['audit_events_after'],
					'Expected audit log entry from handle().'
				);
			}
		} else {
			$test->assertFalse( $result['handle_success'], 'Expected handle() to reject submission.' );
			if ( ! empty( $handle_opts['expect_submission_saved'] ) ) {
				$test->assertGreaterThan(
					$result['submissions_before'],
					$result['submissions_after']
				);
			} else {
				$test->assertSame(
					$result['submissions_before'],
					$result['submissions_after'],
					'Validation failure should not save a submission row.'
				);
			}
		}

		if ( ! empty( $handle_opts['expect_redirect'] ) ) {
			$test->assertSame(
				(string) $handle_opts['expect_redirect'],
				(string) ( $result['redirect_url'] ?? '' )
			);
		}

		if ( ! empty( $handle_opts['expect_notification_to'] ) ) {
			global $clefa_test_mails;
			$found = false;
			foreach ( (array) $clefa_test_mails as $mail ) {
				if ( ( $mail['to'] ?? '' ) === $handle_opts['expect_notification_to'] ) {
					$found = true;
					break;
				}
			}
			$test->assertTrue( $found, 'Expected notification email to ' . $handle_opts['expect_notification_to'] );
		}
	}

	private function prepare_config( array $config, array $case ): array {
		$config['settings'] = array_merge(
			array(
				'enable_antispam'           => false,
				'block_repeated_submission' => false,
			),
			$config['settings'] ?? array(),
			(array) ( $case['settings'] ?? array() )
		);
		$config['id'] = 1;
		return $config;
	}

	private function apply_settings_overrides( array $case ): void {
		$defaults = array(
			'enable_submission_storage' => true,
			'enable_rate_limiting'      => false,
		);
		CLEFA_Settings_Page::$overrides = array_merge(
			$defaults,
			(array) ( $case['settings_overrides'] ?? array() )
		);
	}

	private function map_handle_response( $response ): array {
		if ( $response instanceof WP_Error ) {
			$data = $response->get_error_data();
			return array(
				'validation_passed' => false,
				'handle_success'    => false,
				'errors'            => $data['errors'] ?? array(),
				'error_code'        => $response->get_error_code(),
				'action_results'    => array(),
				'submission_id'     => 0,
				'redirect_url'      => '',
			);
		}

		return array(
			'validation_passed' => true,
			'handle_success'    => ! empty( $response['success'] ),
			'errors'            => array(),
			'error_code'        => '',
			'action_results'    => $response['action_results'] ?? array(),
			'submission_id'     => (int) ( $response['submission_id'] ?? 0 ),
			'redirect_url'      => (string) ( $response['redirect_url'] ?? '' ),
		);
	}
}
