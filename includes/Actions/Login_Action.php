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

		// _clefa_remember_me is an auto-injected checkbox; JS sends checked values as
		// an array ['1'], unchecked as []. Treat any non-empty value as true.
		if ( ! empty( $cfg['show_remember_me'] ) ) {
			$rm_val   = $data['_clefa_remember_me'] ?? array();
			$remember = ! empty( $rm_val );
		} else {
			$remember = false;
		}

		if ( empty( $username ) || empty( $password ) ) {
			return array(
				'success' => false,
				'message' => __( 'Username and password are required for login.', 'codelinden-elementor-form-addon' ),
			);
		}

		// Authenticate the user first so we can set cookies explicitly.
		// wp_signon() may not reliably send Set-Cookie headers from a REST context.
		$user = wp_authenticate( sanitize_text_field( $username ), $password );

		if ( is_wp_error( $user ) ) {
			return array( 'success' => false, 'message' => $user->get_error_message() );
		}

		// Force auth cookies even inside a REST API request.
		add_filter( 'send_auth_cookies', '__return_true' );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		return array( 'success' => true, 'user_id' => $user->ID );
	}
}
