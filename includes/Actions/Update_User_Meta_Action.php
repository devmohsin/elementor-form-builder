<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

class CLEFA_Update_User_Meta_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		if ( ! is_user_logged_in() && empty( $action_config['user_id_field'] ) ) {
			return array( 'success' => false, 'message' => __( 'User must be logged in for update_user_meta action.', 'codelinden-elementor-form-addon' ) );
		}

		$user_id = get_current_user_id();
		if ( ! empty( $action_config['user_id_field'] ) ) {
			$uid = absint( $data[ $action_config['user_id_field'] ] ?? 0 );
			if ( $uid ) {
				$user_id = $uid;
			}
		}

		if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid user.', 'codelinden-elementor-form-addon' ) );
		}

		$mappings = $action_config['meta_mappings'] ?? array();
		$updated  = array();

		foreach ( (array) $mappings as $map ) {
			$meta_key   = sanitize_key( $this->resolve_token( $map['meta_key'] ?? '', $data, $form_config ) );
			$field_id   = $map['field_id']  ?? '';
			$static_val = $map['static_val'] ?? null;

			if ( ! $meta_key ) { continue; }

			$value = $static_val ?? ( $data[ $field_id ] ?? '' );
			$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( (string) $this->resolve_token( (string) $value, $data, $form_config ) );

			$allowed_meta = apply_filters( 'clefa_update_user_meta_allowed', array(), $user_id, $form_config );
			if ( ! empty( $allowed_meta ) && ! in_array( $meta_key, $allowed_meta, true ) ) { continue; }

			update_user_meta( $user_id, $meta_key, $value );
			$updated[] = $meta_key;
		}

		// Also update core fields if mapped
		$core_mappings = $action_config['core_field_mappings'] ?? array();
		if ( ! empty( $core_mappings ) ) {
			$user_update = array( 'ID' => $user_id );
			$core_fields = array( 'user_email', 'display_name', 'first_name', 'last_name', 'user_url', 'description' );
			foreach ( (array) $core_mappings as $cm ) {
				$core_field = $cm['core_field'] ?? '';
				$field_id   = $cm['field_id']   ?? '';
				if ( in_array( $core_field, $core_fields, true ) && ! empty( $data[ $field_id ] ) ) {
					$user_update[ $core_field ] = sanitize_text_field( (string) $data[ $field_id ] );
				}
			}
			if ( count( $user_update ) > 1 ) {
				wp_update_user( $user_update );
			}
		}

		do_action( 'clefa_update_user_meta_action_done', $user_id, $updated, $data, $form_config );

		return array( 'success' => true, 'updated_keys' => $updated );
	}
}
