<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

class CLEFA_Create_Post_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$r = $this->resolve_all_tokens( $action_config, $data, $form_config );

		$post_type   = sanitize_key( $r['post_type']   ?? 'post' );
		$post_status = sanitize_key( $r['post_status'] ?? 'draft' );
		$post_title  = sanitize_text_field( $r['post_title'] ?? '' );
		$post_author = get_current_user_id() ?: 1;

		if ( empty( $post_title ) ) {
			$title_field = $r['post_title_field'] ?? '';
			$post_title  = sanitize_text_field( $data[ $title_field ] ?? '' );
		}

		$content_field = $action_config['post_content_field'] ?? '';
		$post_content  = '';
		if ( $content_field && ! empty( $data[ $content_field ] ) ) {
			$post_content = wp_kses_post( (string) $data[ $content_field ] );
		} elseif ( ! empty( $r['post_content'] ) ) {
			$post_content = wp_kses_post( $r['post_content'] );
		}

		$excerpt_field = $action_config['post_excerpt_field'] ?? '';
		$post_excerpt  = '';
		if ( $excerpt_field && ! empty( $data[ $excerpt_field ] ) ) {
			$post_excerpt = sanitize_textarea_field( (string) $data[ $excerpt_field ] );
		}

		if ( ! empty( $r['post_author_field'] ) && ! empty( $data[ $r['post_author_field'] ] ) ) {
			$post_author = absint( $data[ $r['post_author_field'] ] );
		}

		$insert_args = array(
			'post_type'    => $post_type,
			'post_status'  => $post_status,
			'post_title'   => $post_title ?: __( 'Form Submission', 'codelinden-elementor-form-addon' ),
			'post_content' => $post_content,
			'post_excerpt' => $post_excerpt,
			'post_author'  => $post_author,
		);

		$insert_args = apply_filters( 'clefa_create_post_args', $insert_args, $data, $form_config );

		$post_id = wp_insert_post( $insert_args, true );
		if ( is_wp_error( $post_id ) ) {
			return array( 'success' => false, 'message' => $post_id->get_error_message() );
		}

		// Meta mappings
		$meta_mappings = $action_config['meta_mappings'] ?? array();
		foreach ( (array) $meta_mappings as $map ) {
			$meta_key   = sanitize_key( $map['meta_key'] ?? '' );
			$field_id   = $map['field_id']  ?? '';
			$static_val = $map['static_val'] ?? null;
			if ( ! $meta_key ) { continue; }
			$value = $static_val ?? ( $data[ $field_id ] ?? '' );
			$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( (string) $value );
			update_post_meta( $post_id, $meta_key, $value );
		}

		// Taxonomy mappings
		$tax_mappings = $action_config['taxonomy_mappings'] ?? array();
		foreach ( (array) $tax_mappings as $tm ) {
			$taxonomy = sanitize_key( $tm['taxonomy'] ?? '' );
			$field_id = $tm['field_id'] ?? '';
			if ( ! $taxonomy || ! $field_id || empty( $data[ $field_id ] ) ) { continue; }
			$terms = is_array( $data[ $field_id ] ) ? array_map( 'intval', $data[ $field_id ] ) : array( intval( $data[ $field_id ] ) );
			wp_set_object_terms( $post_id, $terms, $taxonomy );
		}

		do_action( 'clefa_create_post_action_done', $post_id, $data, $form_config );

		return array( 'success' => true, 'post_id' => $post_id );
	}
}
