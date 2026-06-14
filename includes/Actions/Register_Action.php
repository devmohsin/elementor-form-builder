<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

class CLEFA_Register_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$map = $this->resolve_all_tokens( $action_config, $data, $form_config );

		$username = $data[ $map['username_field'] ?? '' ] ?? ( $map['username'] ?? '' );
		$email    = $data[ $map['email_field']    ?? '' ] ?? ( $map['email']    ?? '' );
		$password = $data[ $map['password_field'] ?? '' ] ?? ( $map['password'] ?? '' );

		$username = sanitize_user( (string) $username );
		$email    = sanitize_email( (string) $email );

		if ( empty( $username ) ) {
			$username = sanitize_user( explode( '@', $email )[0] . rand( 100, 999 ) );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			return array( 'success' => false, 'message' => __( 'A valid email address is required to register.', 'codelinden-elementor-form-addon' ) );
		}

		if ( email_exists( $email ) ) {
			return array( 'success' => false, 'message' => __( 'This email is already registered.', 'codelinden-elementor-form-addon' ) );
		}

		if ( username_exists( $username ) ) {
			$username .= rand( 10, 99 );
		}

		if ( empty( $password ) ) {
			$password = wp_generate_password();
		}

		$user_data = array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => $password,
			'role'       => sanitize_key( $map['role'] ?? 'subscriber' ),
		);

		if ( ! empty( $map['first_name_field'] ) && ! empty( $data[ $map['first_name_field'] ] ) ) {
			$user_data['first_name'] = sanitize_text_field( $data[ $map['first_name_field'] ] );
		}
		if ( ! empty( $map['last_name_field'] ) && ! empty( $data[ $map['last_name_field'] ] ) ) {
			$user_data['last_name'] = sanitize_text_field( $data[ $map['last_name_field'] ] );
		}
		if ( ! empty( $map['display_name_field'] ) && ! empty( $data[ $map['display_name_field'] ] ) ) {
			$user_data['display_name'] = sanitize_text_field( $data[ $map['display_name_field'] ] );
		}

		$user_data = apply_filters( 'clefa_register_action_user_data', $user_data, $data, $form_config );

		$user_id = wp_insert_user( $user_data );
		if ( is_wp_error( $user_id ) ) {
			return array( 'success' => false, 'message' => $user_id->get_error_message() );
		}

		// Map extra meta fields
		$meta_fields = $map['meta_fields'] ?? array();
		if ( is_array( $meta_fields ) ) {
			foreach ( $meta_fields as $meta ) {
				$meta_key   = sanitize_key( $meta['meta_key'] ?? '' );
				$field_id   = $meta['field_id'] ?? '';
				$meta_value = $data[ $field_id ] ?? '';
				if ( $meta_key && $field_id ) {
					update_user_meta( $user_id, $meta_key, sanitize_text_field( (string) $meta_value ) );
				}
			}
		}

		// Auto login
		if ( ! empty( $map['auto_login'] ) && ! is_user_logged_in() ) {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id );
		}

		do_action( 'clefa_register_action_user_registered', $user_id, $data, $form_config );

		return array( 'success' => true, 'user_id' => $user_id );
	}
}
