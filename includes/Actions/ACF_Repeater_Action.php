<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

/**
 * Writes a native form repeater field value to an ACF repeater field.
 *
 * Config keys:
 *   acf_field_key   string  ACF repeater field key or name.
 *   repeater_field  string  Form field_id containing row arrays.
 *   target_type     string  post | user | option | term (default post).
 *   target_id_field string  Field whose value is the target ID.
 *   target_id       int     Static target ID when target_id_field is empty.
 *   row_mappings    array   [ { acf_sub_key, field_id } ] maps form sub-fields to ACF sub-fields.
 *                           When empty, each row array is passed through as-is.
 */
class CLEFA_ACF_Repeater_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		if ( ! function_exists( 'update_field' ) ) {
			return array( 'success' => false, 'message' => 'ACF plugin is not active.' );
		}

		$acf_field      = sanitize_text_field( $action_config['acf_field_key'] ?? '' );
		$repeater_field = sanitize_key( $action_config['repeater_field'] ?? '' );

		if ( ! $acf_field || ! $repeater_field ) {
			return array( 'success' => false, 'message' => 'ACF repeater field and form repeater field are required.' );
		}

		$raw_rows = $data[ $repeater_field ] ?? array();
		if ( ! is_array( $raw_rows ) ) {
			$raw_rows = array();
		}

		$rows = $this->build_acf_rows( $raw_rows, $action_config['row_mappings'] ?? array() );

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
				if ( ! $target_id ) {
					$target_id = get_current_user_id();
				}
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

		update_field( $acf_field, $rows, $acf_target );

		do_action( 'clefa_after_acf_repeater_action', $acf_field, $rows, $acf_target, $data, $form_config );

		return array(
			'success' => true,
			'field'   => $acf_field,
			'target'  => $acf_target,
			'rows'    => count( $rows ),
		);
	}

	private function build_acf_rows( array $raw_rows, array $mappings ): array {
		if ( empty( $mappings ) ) {
			return array_values( array_filter( $raw_rows, 'is_array' ) );
		}

		$rows = array();
		foreach ( $raw_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$acf_row = array();
			foreach ( (array) $mappings as $map ) {
				$acf_key = sanitize_key( $map['acf_sub_key'] ?? $map['acf_field_key'] ?? '' );
				$fid     = sanitize_key( $map['field_id'] ?? $map['form_sub_field'] ?? '' );
				if ( $acf_key && array_key_exists( $fid, $row ) ) {
					$acf_row[ $acf_key ] = $row[ $fid ];
				}
			}
			if ( ! empty( $acf_row ) ) {
				$rows[] = $acf_row;
			}
		}

		return $rows;
	}
}
