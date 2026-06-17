<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Form_Submission_Handler {

	public function handle( WP_REST_Request $request ) {
		$form_id     = absint( $request->get_param( 'form_id' ) );
		$instance_id = sanitize_text_field( $request->get_param( 'instance_id' ) ?? '' );
		$raw_data    = $request->get_param( 'data' );

		if ( ! $form_id ) {
			return new WP_Error( 'clefa_missing_form_id', __( 'Form ID is required.', 'codelinden-elementor-form-addon' ), array( 'status' => 400 ) );
		}

		$form = CLEFA_Tables::get_form( $form_id );
		if ( ! $form ) {
			return new WP_Error( 'clefa_form_not_found', __( 'Form not found.', 'codelinden-elementor-form-addon' ), array( 'status' => 404 ) );
		}

		// Accept both 'published' and 'active' as valid statuses
		$status = $form['status'] ?? '';
		if ( ! in_array( $status, array( 'published', 'active' ), true ) ) {
			return new WP_Error( 'clefa_form_inactive', __( 'This form is not active.', 'codelinden-elementor-form-addon' ), array( 'status' => 403 ) );
		}

		$config = is_array( $form['config'] ?? null ) ? $form['config'] : array();
		$config = apply_filters( 'clefa_submission_form_config', $config, $form_id );
		$config['id'] = $form_id;

		$settings = $config['settings'] ?? array();

		// Login requirement
		if ( ! empty( $settings['require_login'] ) && ! is_user_logged_in() ) {
			return new WP_Error( 'clefa_login_required', __( 'You must be logged in to submit this form.', 'codelinden-elementor-form-addon' ), array( 'status' => 401 ) );
		}

		// Role restriction
		if ( is_user_logged_in() && ! empty( $settings['allowed_roles'] ) ) {
			$allowed = array_filter( (array) $settings['allowed_roles'] );
			if ( ! empty( $allowed ) ) {
				$user = wp_get_current_user();
				if ( ! array_intersect( $user->roles, $allowed ) ) {
					return new WP_Error( 'clefa_permission_denied', __( 'You do not have the required role to submit this form.', 'codelinden-elementor-form-addon' ), array( 'status' => 403 ) );
				}
			}
		}

		// Antispam: honeypot
		if ( ! empty( $settings['enable_antispam'] ) ) {
			$hp_key = 'clefa_hp_' . ( $form['form_uuid'] ?? '' );
			$hp_val = $request->get_param( $hp_key );
			if ( ! empty( $hp_val ) ) {
				return rest_ensure_response( array(
					'success' => true,
					'message' => wp_kses_post( $settings['success_message'] ?? __( 'Form submitted successfully.', 'codelinden-elementor-form-addon' ) ),
				) );
			}

			// Time-to-submit check
			$ts_token    = sanitize_text_field( $request->get_param( '_clefa_ts' ) ?? '' );
			$min_seconds = max( 1, absint( $settings['min_submit_seconds'] ?? 3 ) );
			if ( $ts_token && ! $this->verify_time_token( $ts_token, $min_seconds ) ) {
				return rest_ensure_response( array(
					'success' => true,
					'message' => wp_kses_post( $settings['success_message'] ?? __( 'Form submitted successfully.', 'codelinden-elementor-form-addon' ) ),
				) );
			}

			// Minimum interaction count check
			// JS sends _clefa_ic (interaction count) as a hidden field
			if ( ! empty( $settings['min_interactions'] ) ) {
				$min_ic = absint( $settings['min_interactions'] );
				$ic     = absint( $request->get_param( '_clefa_ic' ) ?? 0 );
				if ( $ic < $min_ic ) {
					return rest_ensure_response( array(
						'success' => true,
						'message' => wp_kses_post( $settings['success_message'] ?? __( 'Form submitted successfully.', 'codelinden-elementor-form-addon' ) ),
					) );
				}
			}

			// User-agent hash check — reject requests with no user-agent (bots)
			if ( ! empty( $settings['require_user_agent'] ) ) {
				$ua = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );
				if ( empty( $ua ) ) {
					return rest_ensure_response( array(
						'success' => true,
						'message' => wp_kses_post( $settings['success_message'] ?? __( 'Form submitted successfully.', 'codelinden-elementor-form-addon' ) ),
					) );
				}
			}
		}

		// Block repeated submission: per IP+form within a configurable window
		if ( ! empty( $settings['block_repeated_submission'] ) ) {
			$ip            = $this->get_user_ip();
			$window        = max( 1, absint( $settings['repeat_block_seconds'] ?? 300 ) ); // seconds
			$dedupe_key    = 'clefa_dedup_' . md5( $ip . '_' . $form_id );
			if ( get_transient( $dedupe_key ) ) {
				return new WP_Error(
					'clefa_duplicate_submission',
					__( 'This form has already been submitted recently. Please wait before submitting again.', 'codelinden-elementor-form-addon' ),
					array( 'status' => 429 )
				);
			}
			set_transient( $dedupe_key, 1, $window );
		}

		// Rate limiting
		if ( CLEFA_Settings_Page::get( 'enable_rate_limiting', false ) ) {
			$rate_error = $this->check_rate_limit( $form_id );
			if ( is_wp_error( $rate_error ) ) {
				return $rate_error;
			}
		}

		$data = is_array( $raw_data ) ? $raw_data : array();

		// Evaluate step routing to get the set of steps actually visited
		$active_step_ids = CLEFA_Form_Routing_Engine::compute_active_steps( $config, $data );
		$active_field_ids = $active_step_ids
			? CLEFA_Form_Routing_Engine::get_active_field_ids( $config, $active_step_ids )
			: null;

		// Evaluate conditions to get visible fields (within active steps only)
		$visible_field_ids = CLEFA_Form_Condition_Engine::get_visible_field_ids( $config, $data );

		// Apply require/unrequire condition overrides to field config before validation
		$required_overrides = CLEFA_Form_Condition_Engine::get_required_overrides( $config, $data );
		if ( ! empty( $required_overrides ) ) {
			$config = $this->apply_required_overrides( $config, $required_overrides );
		}

		// Validate — only fields in visited steps and visible conditions
		$effective_visible = $visible_field_ids;
		if ( $active_field_ids !== null ) {
			$effective_visible = $effective_visible !== null
				? array_values( array_intersect( $effective_visible, $active_field_ids ) )
				: $active_field_ids;
		}

		do_action( 'clefa_before_validation', $form_id, $data, $config );

		$validator = new CLEFA_Form_Validator();
		$errors    = $validator->validate( $data, $config, $effective_visible );

		do_action( 'clefa_after_validation', $form_id, $data, $errors, $config );

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'clefa_validation_failed',
				__( 'Please correct the errors below.', 'codelinden-elementor-form-addon' ),
				array(
					'status' => 422,
					'errors' => $errors,
				)
			);
		}

		// Sanitize
		$sanitized_data = CLEFA_Form_Sanitizer::sanitize( $data, $config );
		$sanitized_data = apply_filters( 'clefa_sanitized_submission_data', $sanitized_data, $data, $config, $form_id );

		// Pass through reserved internal fields (prefixed _clefa_) that are not
		// registered form fields and therefore skipped by the sanitizer.
		foreach ( $data as $key => $val ) {
			if ( str_starts_with( $key, '_clefa_' ) && ! isset( $sanitized_data[ $key ] ) ) {
				$sanitized_data[ $key ] = $val;
			}
		}

		do_action( 'clefa_before_submission_save', $form_id, $sanitized_data, $config );

		// Save submission — never store login-form attempts
		$submission_id = 0;
		$form_type     = $config['form_type'] ?? 'standard';
		$store_enabled = CLEFA_Settings_Page::get( 'enable_submission_storage', true );
		if ( $store_enabled && 'login' !== $form_type ) {
			$submission_id = $this->save_submission( $form, $sanitized_data, $data, $config, $instance_id );
		}

		// Run actions
		$actions = $config['actions'] ?? array();
		if ( empty( $actions ) ) {
			$actions = array( array( 'action_type' => 'save_submission', 'enabled' => true ) );
		}

		require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';
		$action_results = CLEFA_Form_Action_Runner::run_actions( $actions, $sanitized_data, $config, $submission_id );

		// Surface failures from critical actions (e.g. login_user) as form errors
		foreach ( $action_results as $action_type => $result ) {
			if ( isset( $result['success'] ) && false === $result['success'] && ! empty( $result['message'] ) ) {
				$error_message = $result['message'];
				// Allow login forms to override the error text shown to the user.
				if ( 'login' === $form_type && ! empty( $settings['login_error_message'] ) ) {
					$error_message = $settings['login_error_message'];
				}
				return new WP_Error( 'clefa_action_failed', $error_message, array( 'status' => 422 ) );
			}
		}

		// Update submission with action results
		if ( $submission_id ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'clefa_submissions',
				array( 'action_results_json' => wp_json_encode( $action_results ) ),
				array( 'id' => $submission_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		do_action( 'clefa_after_submission_save', $form_id, $sanitized_data, $submission_id, $action_results );

		// Build response
		$redirect_url = $settings['redirect_url'] ?? '';
		foreach ( $action_results as $res ) {
			if ( ! empty( $res['redirect_url'] ) ) {
				$redirect_url = $res['redirect_url'];
				break;
			}
		}

		$raw_success = $settings['success_message'] ?? '';
		if ( '' === $raw_success ) {
			$raw_success = 'login' === $form_type
				? __( 'Login successful', 'codelinden-elementor-form-addon' )
				: CLEFA_Settings_Page::get( 'default_success_message', __( 'Form submitted successfully.', 'codelinden-elementor-form-addon' ) );
		}
		$success_message = wp_kses_post( $raw_success );

		$response = array(
			'success'        => true,
			'submission_id'  => $submission_id,
			'message'        => $success_message,
			'message_html'   => CLEFA_Form_Renderer::render_notice( $success_message, 'success', $form_id ),
			'redirect_url'   => $redirect_url ? esc_url( $redirect_url ) : '',
			'action_results' => $action_results,
			'event_payload'  => array(
				'form_id'       => $form_id,
				'submission_id' => $submission_id,
				'timestamp'     => current_time( 'c' ),
			),
		);

		CLEFA_Audit_Log::write( 'form_submitted', array(
			'form_id'       => $form_id,
			'submission_id' => $submission_id,
		) );

		return rest_ensure_response( apply_filters( 'clefa_submission_response', $response, $submission_id, $form_id ) );
	}

	private function save_submission( array $form, array $sanitized_data, array $raw_data, array $config, $instance_id ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'clefa_submissions',
			array(
				'form_id'              => absint( $form['id'] ),
				'form_uuid'            => sanitize_text_field( $form['form_uuid'] ?? '' ),
				'form_instance_id'     => sanitize_text_field( $instance_id ),
				'user_id'              => get_current_user_id(),
				'status'               => 'complete',
				'source_url'           => esc_url_raw( $_SERVER['HTTP_REFERER'] ?? '' ),
				'ip_hash'              => hash( 'sha256', $this->get_user_ip() . wp_salt( 'auth' ) ),
				'user_agent_hash'      => hash( 'sha256', sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
				'submitted_data_json'  => wp_json_encode( $raw_data ),
				'sanitized_data_json'  => wp_json_encode( $sanitized_data ),
				'action_results_json'  => wp_json_encode( array() ),
				'created_at'           => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	private function get_user_ip() {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$ip = trim( explode( ',', sanitize_text_field( $_SERVER[ $k ] ) )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	/**
	 * Apply require/unrequire condition overrides to the field config so the
	 * validator sees the correct required state for each field.
	 *
	 * @param array $config    Full form config.
	 * @param array $overrides Map of field_id => bool (true = required, false = optional).
	 * @return array           Config with updated required flags.
	 */
	private function apply_required_overrides( array $config, array $overrides ): array {
		foreach ( ( $config['steps'] ?? array() ) as $si => $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $fi => $field ) {
				$id = $field['field_id'] ?? '';
				if ( isset( $overrides[ $id ] ) ) {
					$config['steps'][ $si ]['fields'][ $fi ]['required'] = $overrides[ $id ];
				}
			}
		}
		return $config;
	}

	/**
	 * Verify the time-to-submit token.
	 * Token format: base64( timestamp:hmac )
	 *
	 * @param string $token      The _clefa_ts value from the request.
	 * @param int    $min_secs   Minimum seconds that must have elapsed.
	 * @return bool  True if the token is valid AND enough time has passed.
	 */
	private function verify_time_token( $token, $min_secs = 3 ) {
		$decoded = base64_decode( $token, true );
		if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
			return false;
		}
		list( $timestamp, $sig ) = explode( ':', $decoded, 2 );
		$timestamp = absint( $timestamp );
		$expected  = hash_hmac( 'sha256', (string) $timestamp, wp_salt( 'nonce' ) );
		if ( ! hash_equals( $expected, $sig ) ) {
			return false; // tampered
		}
		$elapsed = time() - $timestamp;
		return $elapsed >= $min_secs;
	}

	/**
	 * Rate-limit submissions per IP per form.
	 *
	 * @param int $form_id
	 * @return true|WP_Error
	 */
	private function check_rate_limit( $form_id ) {
		$ip       = $this->get_user_ip();
		$max      = max( 1, absint( CLEFA_Settings_Page::get( 'rate_limit_max', 5 ) ) );
		$window   = max( 1, absint( CLEFA_Settings_Page::get( 'rate_limit_window', 60 ) ) ); // minutes
		$key      = 'clefa_rl_' . md5( $ip . '_' . $form_id );
		$attempts = (int) get_transient( $key );

		if ( $attempts >= $max ) {
			return new WP_Error(
				'clefa_rate_limited',
				/* translators: %d is the window in minutes */
				sprintf( __( 'Too many submissions. Please wait %d minutes before trying again.', 'codelinden-elementor-form-addon' ), $window ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $attempts + 1, $window * MINUTE_IN_SECONDS );
		return true;
	}
}
