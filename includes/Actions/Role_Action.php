<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

/**
 * Action: assign or add a WordPress role to a user on form submission.
 *
 * Config keys:
 *  target         string  current_user | {field:field_id}  (resolves to user_id or login/email)
 *  role           string  role slug, supports tokens
 *  mode           string  replace (default) | add
 *  meta_key       string  optional user meta key to update after role assignment
 *  meta_value     string  value for the meta key (supports tokens), default 'complete'
 *  condition      array   optional condition check before running (field, operator, value)
 */
class CLEFA_Role_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$target_cfg = $action_config['target'] ?? 'current_user';
		$role       = sanitize_key( $this->resolve( $action_config['role'] ?? '', $data ) );
		$mode       = in_array( $action_config['mode'] ?? 'replace', array( 'replace', 'add' ), true )
			? $action_config['mode']
			: 'replace';

		if ( ! $role ) {
			return array( 'success' => false, 'error' => 'No role specified.' );
		}

		// Optional condition gate
		if ( ! empty( $action_config['condition'] ) ) {
			$cond       = $action_config['condition'];
			$field_val  = $data[ $cond['field'] ?? '' ] ?? '';
			if ( ! CLEFA_Form_Condition_Engine::compare( $field_val, $cond['operator'] ?? 'equals', $cond['value'] ?? '' ) ) {
				return array( 'success' => true, 'skipped' => true );
			}
		}

		$user_id = $this->resolve_target_user_id( $target_cfg, $data );
		if ( ! $user_id ) {
			return array( 'success' => false, 'error' => 'Target user not found.' );
		}

		// Permission: current user can edit this user or is the user themselves
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
			return array( 'success' => false, 'error' => 'Permission denied.' );
		}

		if ( ! get_role( $role ) ) {
			return array( 'success' => false, 'error' => 'Role "' . esc_html( $role ) . '" does not exist.' );
		}

		$user = new WP_User( $user_id );

		if ( 'replace' === $mode ) {
			$user->set_role( $role );
		} else {
			$user->add_role( $role );
		}

		if ( ! empty( $action_config['meta_key'] ) ) {
			$meta_key = sanitize_key( $action_config['meta_key'] );
			$meta_val = sanitize_text_field(
				$this->resolve( $action_config['meta_value'] ?? 'complete', $data )
			);
			update_user_meta( $user_id, $meta_key, $meta_val );
		}

		return array(
			'success' => true,
			'user_id' => $user_id,
			'role'    => $role,
			'mode'    => $mode,
		);
	}

	/**
	 * Resolve target user ID from config.
	 *
	 * @param string $target_cfg 'current_user' or '{field:field_id}'
	 * @param array  $data
	 * @return int|null
	 */
	private function resolve_target_user_id( $target_cfg, array $data ) {
		if ( 'current_user' === $target_cfg || empty( $target_cfg ) ) {
			$uid = get_current_user_id();
			return $uid ?: null;
		}

		// Resolve token to a value
		$resolved = $this->resolve( $target_cfg, $data );
		if ( is_numeric( $resolved ) ) {
			return absint( $resolved );
		}

		// Try by login or email
		$user = is_email( $resolved )
			? get_user_by( 'email', $resolved )
			: get_user_by( 'login', $resolved );

		return $user ? $user->ID : null;
	}
}
