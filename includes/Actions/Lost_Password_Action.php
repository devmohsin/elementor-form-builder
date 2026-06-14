<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

class CLEFA_Lost_Password_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$login_field = $action_config['login_field'] ?? '';
		$login       = '';

		if ( $login_field ) {
			$login = trim( $data[ $login_field ] ?? '' );
		}

		if ( ! $login ) {
			foreach ( array( 'email', 'user_email', 'username', 'user_login', 'login' ) as $key ) {
				if ( ! empty( $data[ $key ] ) ) {
					$login = trim( $data[ $key ] );
					break;
				}
			}
		}

		if ( ! $login ) {
			return array(
				'success' => false,
				'message' => __( 'No email or username provided for password reset.', 'codelinden-elementor-form-addon' ),
			);
		}

		$user_data = false;
		if ( false !== strpos( $login, '@' ) ) {
			$user_data = get_user_by( 'email', sanitize_email( $login ) );
		}
		if ( ! $user_data ) {
			$user_data = get_user_by( 'login', sanitize_user( $login ) );
		}

		if ( ! $user_data ) {
			$error_message = $action_config['not_found_message'] ?? __( 'No account was found with that email or username.', 'codelinden-elementor-form-addon' );
			$error_message = $this->resolve_token( $error_message, $data, $form_config );
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		// Use WordPress built-in password reset mechanism
		$reset = retrieve_password( $user_data->user_login );

		if ( is_wp_error( $reset ) ) {
			return array(
				'success' => false,
				'message' => $reset->get_error_message(),
			);
		}

		do_action( 'clefa_after_lost_password', $user_data, $data, $form_config );

		$success_message = $action_config['success_message'] ?? __( 'Password reset instructions have been sent to your email.', 'codelinden-elementor-form-addon' );
		$success_message = $this->resolve_token( $success_message, $data, $form_config );

		return array(
			'success'  => true,
			'message'  => $success_message,
			'redirect' => $this->resolve_token( $action_config['redirect_url'] ?? '', $data, $form_config ),
		);
	}
}
