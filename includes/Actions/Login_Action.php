<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

class CLEFA_Login_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		if ( is_user_logged_in() ) {
			return array( 'success' => true, 'message' => 'already_logged_in' );
		}

		$cfg            = $action_config['config'] ?? $action_config;
		$username_field = $cfg['username_field'] ?? '';
		$password_field = $cfg['password_field'] ?? '';

		$username = trim( $data[ $username_field ] ?? '' );
		$password = $data[ $password_field ] ?? '';

		// When "Show Remember Me checkbox" is enabled, the auto-rendered checkbox
		// submits under the reserved key _clefa_remember_me. Fall back to false.
		if ( ! empty( $cfg['show_remember_me'] ) ) {
			$remember = ! empty( $data['_clefa_remember_me'] );
		} else {
			$remember = false;
		}

		if ( empty( $username ) || empty( $password ) ) {
			return array(
				'success' => false,
				'message' => __( 'Username and password are required for login.', 'codelinden-elementor-form-addon' ),
			);
		}

		$credentials = array(
			'user_login'    => sanitize_text_field( $username ),
			'user_password' => $password,
			'remember'      => $remember,
		);

		$user = wp_signon( $credentials, is_ssl() );

		if ( is_wp_error( $user ) ) {
			return array( 'success' => false, 'message' => $user->get_error_message() );
		}

		return array( 'success' => true, 'user_id' => $user->ID );
	}
}
