<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

class CLEFA_Update_Post_Meta_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$post_id = absint( $this->resolve_token( $action_config['post_id'] ?? '', $data, $form_config ) );

		if ( ! $post_id ) {
			$post_id_field = $action_config['post_id_field'] ?? '';
			$post_id       = absint( $data[ $post_id_field ] ?? 0 );
		}

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return array( 'success' => false, 'message' => __( 'Post not found for update_post_meta action.', 'codelinden-elementor-form-addon' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'success' => false, 'message' => __( 'You do not have permission to edit this post.', 'codelinden-elementor-form-addon' ) );
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

			update_post_meta( $post_id, $meta_key, $value );
			$updated[] = $meta_key;
		}

		do_action( 'clefa_update_post_meta_action_done', $post_id, $updated, $data, $form_config );

		return array( 'success' => true, 'updated_keys' => $updated );
	}
}
