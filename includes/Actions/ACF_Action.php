<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

/**
 * Updates an Advanced Custom Fields (ACF) field value.
 *
 * Gracefully skips if ACF is not active (function_exists check on update_field).
 *
 * Action config keys:
 *   target_type       (string)  'post' (default) | 'user' | 'option' | 'term'.
 *   target_id_field   (string)  Field ID whose value is the post/user/term ID.
 *   target_id         (int)     Static target ID.
 *   acf_field_key     (string)  ACF field key (e.g. 'field_5f4d3c2b1a') or field name.
 *   value_field       (string)  Form field ID whose value to write.
 *   value             (string)  Static value (token-resolved; used when value_field empty).
 */
class CLEFA_ACF_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		if ( ! function_exists( 'update_field' ) ) {
			return array( 'success' => false, 'message' => 'ACF plugin is not active.' );
		}

		$acf_field = sanitize_text_field( $action_config['acf_field_key'] ?? '' );
		if ( ! $acf_field ) {
			return array( 'success' => false, 'message' => 'No ACF field key/name provided.' );
		}

		// Resolve value
		$value_field = $action_config['value_field'] ?? '';
		if ( $value_field && array_key_exists( $value_field, $data ) ) {
			$value = $data[ $value_field ];
		} else {
			$value = $this->resolve_token( $action_config['value'] ?? '', $data, $form_config );
		}

		// Resolve target
		$target_type     = $action_config['target_type'] ?? 'post';
		$target_id_field = $action_config['target_id_field'] ?? '';
		$target_id       = 0;

		if ( $target_id_field && isset( $data[ $target_id_field ] ) ) {
			$target_id = absint( $data[ $target_id_field ] );
		} elseif ( ! empty( $action_config['target_id'] ) ) {
			$target_id = absint( $this->resolve_token( (string) $action_config['target_id'], $data, $form_config ) );
		}

		switch ( $target_type ) {
			case 'user':
				if ( ! $target_id ) { $target_id = get_current_user_id(); }
				$acf_target = 'user_' . $target_id;
				break;
			case 'term':
				$acf_target = 'term_' . $target_id;
				break;
			case 'option':
				$acf_target = 'option';
				break;
			case 'post':
			default:
				$acf_target = $target_id ?: false;
				break;
		}

		if ( false === $acf_target || ( is_numeric( $acf_target ) && ! $acf_target ) ) {
			return array( 'success' => false, 'message' => 'No valid ACF target resolved.' );
		}

		$result = update_field( $acf_field, $value, $acf_target );

		if ( ! $result ) {
			// update_field returns false if the value is unchanged — not necessarily an error
			return array(
				'success' => true,
				'message' => 'ACF field unchanged (value identical or field not found).',
				'field'   => $acf_field,
				'target'  => $acf_target,
			);
		}

		do_action( 'clefa_after_acf_action', $acf_field, $value, $acf_target, $data, $form_config );

		return array(
			'success' => true,
			'field'   => $acf_field,
			'target'  => $acf_target,
		);
	}
}
