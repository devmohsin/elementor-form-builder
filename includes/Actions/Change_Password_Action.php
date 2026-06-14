<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

/**
 * Updates a WordPress user password from a form submission.
 *
 * Intended for profile / password-change forms after confirm_password validation.
 *
 * Config keys:
 *   password_field  string  Field ID containing the new password (default password).
 *   user_id_field   string  Optional field with target user ID; defaults to current user.
 */
class CLEFA_Change_Password_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$user_id = get_current_user_id();

		if ( ! empty( $action_config['user_id_field'] ) ) {
			$uid = absint( $data[ $action_config['user_id_field'] ] ?? 0 );
			if ( $uid ) {
				$user_id = $uid;
			}
		}

		if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid user.', 'codelinden-elementor-form-addon' ),
			);
		}

		if ( ! is_user_logged_in() && empty( $action_config['user_id_field'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'User must be logged in to change password.', 'codelinden-elementor-form-addon' ),
			);
		}

		$pw_field = sanitize_key( $action_config['password_field'] ?? 'password' );
		$password = (string) ( $data[ $pw_field ] ?? '' );

		if ( '' === $password ) {
			return array(
				'success' => false,
				'message' => __( 'No password provided.', 'codelinden-elementor-form-addon' ),
			);
		}

		if ( ! function_exists( 'wp_set_password' ) ) {
			return array( 'success' => false, 'message' => 'wp_set_password is unavailable.' );
		}

		wp_set_password( $password, $user_id );

		do_action( 'clefa_change_password_action_done', $user_id, $data, $form_config );

		return array(
			'success' => true,
			'user_id' => $user_id,
		);
	}
}
