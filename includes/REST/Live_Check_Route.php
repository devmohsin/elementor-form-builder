<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Live_Check_Route {

	const NAMESPACE = 'clefa/v1';

	public function register() {
		register_rest_route( self::NAMESPACE, '/live-check', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'form_id'    => array( 'sanitize_callback' => 'absint' ),
				'field_id'   => array( 'sanitize_callback' => 'sanitize_key' ),
				'check_type' => array( 'sanitize_callback' => 'sanitize_key' ),
				'value'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );
	}

	public function handle( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'codelinden-elementor-form-addon' ), array( 'status' => 403 ) );
		}

		$form_id    = absint( $request->get_param( 'form_id' ) );
		$field_id   = sanitize_key( $request->get_param( 'field_id' ) );
		$check_type = sanitize_key( $request->get_param( 'check_type' ) );
		$value      = sanitize_text_field( $request->get_param( 'value' ) );

		if ( empty( $value ) ) {
			return rest_ensure_response( array( 'available' => null, 'message' => '' ) );
		}

		switch ( $check_type ) {
			case 'username_available':
				$available = ! username_exists( $value );
				$message   = $available
					? __( 'Username is available.', 'codelinden-elementor-form-addon' )
					: __( 'Username is already taken.', 'codelinden-elementor-form-addon' );
				break;

			case 'email_available':
				$available = ! email_exists( $value );
				$message   = $available
					? __( 'Email is available.', 'codelinden-elementor-form-addon' )
					: __( 'This email address is already registered.', 'codelinden-elementor-form-addon' );
				break;

			case 'email_exists':
				$available = (bool) email_exists( $value );
				$message   = $available
					? __( 'Account found.', 'codelinden-elementor-form-addon' )
					: __( 'No account found with that email.', 'codelinden-elementor-form-addon' );
				break;

			default:
				// Allow custom check types via filter
				$result = apply_filters( 'clefa_live_check_' . $check_type, null, $value, $form_id, $field_id, $request );
				if ( null === $result ) {
					return new WP_Error( 'unknown_check_type', __( 'Unknown check type.', 'codelinden-elementor-form-addon' ), array( 'status' => 400 ) );
				}
				if ( is_array( $result ) ) {
					$available = (bool) ( $result['available'] ?? false );
					$message   = sanitize_text_field( $result['message'] ?? '' );
				} else {
					$available = (bool) $result;
					$message   = '';
				}
				break;
		}

		CLEFA_Audit_Log::write( 'live_check', array(
			'form_id'    => $form_id,
			'field_id'   => $field_id,
			'check_type' => $check_type,
			'available'  => $available,
		) );

		return rest_ensure_response( array(
			'available' => $available,
			'message'   => $message,
		) );
	}
}
