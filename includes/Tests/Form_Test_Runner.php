<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Form_Test_Runner {

	public function run( $form_id, array $test_cases ) {
		require_once CLEFA_PLUGIN_PATH . 'includes/Core/Testing.php';
		CLEFA_Testing::enable();

		$form = CLEFA_Tables::get_form( $form_id );
		if ( ! $form ) {
			return new WP_Error( 'form_not_found', __( 'Form not found.', 'codelinden-elementor-form-addon' ) );
		}

		$config = is_array( $form['config'] ?? null ) ? $form['config'] : array();
		$config['id'] = $form_id;

		$group_id = 'test-' . wp_generate_uuid4();
		$results  = array();

		foreach ( $test_cases as $tc ) {
			$results[] = $this->run_single( $form, $config, $tc, $group_id );
		}

		return array(
			'group_id' => $group_id,
			'form_id'  => $form_id,
			'results'  => $results,
			'summary'  => $this->build_summary( $results ),
		);
	}

	private function run_single( array $form, array $config, array $tc, $group_id ) {
		global $wpdb;

		$test_name   = sanitize_text_field( $tc['name'] ?? 'Unnamed Test' );
		$input_data  = is_array( $tc['data'] ?? null ) ? $tc['data'] : array();
		$expect_pass = ! empty( $tc['expect_pass'] );
		$skip_actions= ! empty( $tc['skip_actions'] );

		// Evaluate conditions
		$visible_ids = CLEFA_Form_Condition_Engine::get_visible_field_ids( $config, $input_data );

		// Validate
		$validator = new CLEFA_Form_Validator();
		$errors    = $validator->validate( $input_data, $config, $visible_ids );

		$passed       = $expect_pass ? empty( $errors ) : ! empty( $errors );
		$test_status  = $passed ? 'passed' : 'failed';

		$actual_result = array(
			'validation_passed' => empty( $errors ),
			'errors'            => $errors,
			'visible_fields'    => $visible_ids,
		);

		// Optionally run actions (dry run - no real save)
		if ( $skip_actions ) {
			$actual_result['actions'] = 'skipped';
		} else {
			$sanitized = CLEFA_Form_Sanitizer::sanitize( $input_data, $config );
			$actions   = $config['actions'] ?? array();

			if ( empty( $errors ) ) {
				// Run in a transaction-like way, then roll back the submission
				$submission_id = $this->save_test_submission( $form, $sanitized, $input_data );
				$action_results = array();
				foreach ( $actions as $action ) {
					if ( empty( $action['enabled'] ) || empty( $action['action_type'] ) ) { continue; }
					$action_type  = $action['action_type'] ?? '';
					$action_class = $this->resolve_action_class( $action_type );
					if ( $action_class ) {
						try {
							$obj = new $action_class();
							$res = $obj->run( $sanitized, $config, $submission_id, $action );
							$action_results[ $action_type ] = array( 'result' => $res, 'error' => null );
						} catch ( \Exception $e ) {
							$action_results[ $action_type ] = array( 'result' => null, 'error' => $e->getMessage() );
						}
					}
				}
				$actual_result['actions']       = $action_results;
				$actual_result['submission_id'] = $submission_id;
			}
		}

		$expected_result = array(
			'validation_pass' => $expect_pass,
			'assertions'      => $tc['assertions'] ?? array(),
		);

		// Custom assertions
		$assertion_results = array();
		foreach ( $tc['assertions'] ?? array() as $assertion ) {
			$assertion_results[] = $this->check_assertion( $assertion, $actual_result );
		}
		$actual_result['assertion_results'] = $assertion_results;
		if ( ! empty( $assertion_results ) ) {
			$passed = $passed && ! in_array( false, array_column( $assertion_results, 'passed' ), true );
			$test_status = $passed ? 'passed' : 'failed';
		}

		// Persist to test_logs
		$wpdb->insert(
			$wpdb->prefix . 'clefa_test_logs',
			array(
				'form_id'              => absint( $form['id'] ),
				'test_group_id'        => $group_id,
				'test_name'            => $test_name,
				'expected_result_json' => wp_json_encode( $expected_result ),
				'actual_result_json'   => wp_json_encode( $actual_result ),
				'status'               => $test_status,
				'created_records_json' => wp_json_encode( array(
					'submission_id' => $actual_result['submission_id'] ?? 0,
				) ),
				'cleanup_status'       => 'pending',
				'created_at'           => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return array(
			'test_name'        => $test_name,
			'status'           => $test_status,
			'passed'           => $passed,
			'errors'           => $errors,
			'visible_fields'   => $visible_ids,
			'actions'          => $actual_result['actions'] ?? null,
			'submission_id'    => $actual_result['submission_id'] ?? 0,
			'assertion_results'=> $assertion_results,
		);
	}

	public function cleanup_test_group( $group_id ) {
		global $wpdb;
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}clefa_test_logs WHERE test_group_id = %s",
				$group_id
			),
			ARRAY_A
		);
		foreach ( $logs as $log ) {
			$created = json_decode( $log['created_records_json'] ?? '{}', true ) ?: array();
			$sub_id  = absint( $created['submission_id'] ?? 0 );
			if ( $sub_id ) {
				$wpdb->delete( $wpdb->prefix . 'clefa_submissions', array( 'id' => $sub_id ), array( '%d' ) );
			}
			$wpdb->update(
				$wpdb->prefix . 'clefa_test_logs',
				array( 'cleanup_status' => 'cleaned' ),
				array( 'id' => $log['id'] ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	public static function get_logs( $form_id, $per_page = 20, $page = 1 ) {
		global $wpdb;
		$offset = ( $page - 1 ) * $per_page;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}clefa_test_logs WHERE form_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$form_id, $per_page, $offset
			),
			ARRAY_A
		);
	}

	public static function count_logs( $form_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}clefa_test_logs WHERE form_id = %d",
			$form_id
		) );
	}

	private function save_test_submission( array $form, array $sanitized, array $raw ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'clefa_submissions',
			array(
				'form_id'             => absint( $form['id'] ),
				'form_uuid'           => sanitize_text_field( $form['form_uuid'] ?? '' ),
				'form_instance_id'    => 'test-' . wp_generate_uuid4(),
				'user_id'             => get_current_user_id(),
				'status'              => 'test',
				'source_url'          => admin_url(),
				'ip_hash'             => hash( 'sha256', '127.0.0.1' ),
				'user_agent_hash'     => hash( 'sha256', 'test-runner' ),
				'submitted_data_json' => wp_json_encode( $raw ),
				'sanitized_data_json' => wp_json_encode( $sanitized ),
				'action_results_json' => wp_json_encode( array() ),
				'created_at'          => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	private function check_assertion( array $assertion, array $actual ) {
		$type    = $assertion['type'] ?? '';
		$field   = $assertion['field'] ?? '';
		$expects = $assertion['value'] ?? null;
		$passed  = false;
		$message = '';

		switch ( $type ) {
			case 'field_visible':
				$passed  = in_array( $field, $actual['visible_fields'] ?? array(), true );
				$message = $passed ? "Field '{$field}' is visible." : "Field '{$field}' is NOT visible (expected visible).";
				break;
			case 'field_hidden':
				$passed  = ! in_array( $field, $actual['visible_fields'] ?? array(), true );
				$message = $passed ? "Field '{$field}' is hidden." : "Field '{$field}' is visible (expected hidden).";
				break;
			case 'field_has_error':
				$passed  = array_key_exists( $field, $actual['errors'] ?? array() );
				$message = $passed ? "Field '{$field}' has a validation error." : "Field '{$field}' has NO error (expected error).";
				break;
			case 'field_no_error':
				$passed  = ! array_key_exists( $field, $actual['errors'] ?? array() );
				$message = $passed ? "Field '{$field}' has no error." : "Field '{$field}' has error: " . ( $actual['errors'][ $field ] ?? '' );
				break;
			case 'action_success':
				$a_res  = $actual['actions'][ $field ] ?? null;
				$passed = $a_res && empty( $a_res['error'] );
				$message= $passed ? "Action '{$field}' succeeded." : "Action '{$field}' did not succeed.";
				break;
			default:
				$passed  = false;
				$message = "Unknown assertion type: {$type}";
		}

		return array(
			'type'    => $type,
			'field'   => $field,
			'passed'  => $passed,
			'message' => $message,
		);
	}

	private function build_summary( array $results ) {
		$total  = count( $results );
		$passed = count( array_filter( $results, function( $r ) { return $r['passed']; } ) );
		return array(
			'total'  => $total,
			'passed' => $passed,
			'failed' => $total - $passed,
		);
	}

	private function resolve_action_class( $type ) {
		$map = array(
			'save_submission'    => 'CLEFA_Save_Submission_Action',
			'send_email'         => 'CLEFA_Send_Email_Action',
			'redirect'           => 'CLEFA_Redirect_Action',
			'login'              => 'CLEFA_Login_Action',
			'register'           => 'CLEFA_Register_Action',
			'update_user_meta'   => 'CLEFA_Update_User_Meta_Action',
			'update_post_meta'   => 'CLEFA_Update_Post_Meta_Action',
			'create_post'        => 'CLEFA_Create_Post_Action',
		);
		$class = apply_filters( 'clefa_action_class', $map[ $type ] ?? null, $type );
		if ( $class && class_exists( $class ) ) { return $class; }
		// Auto-load from Actions directory
		if ( $class ) {
			$file = CLEFA_PLUGIN_PATH . 'includes/Actions/' . str_replace( 'CLEFA_', '', $class ) . '.php';
			if ( file_exists( $file ) ) { require_once $file; }
			if ( class_exists( $class ) ) { return $class; }
		}
		return null;
	}
}
