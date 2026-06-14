<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

/**
 * Assigns, appends, or removes taxonomy terms on a WordPress post.
 *
 * Action config keys:
 *   post_id_field   (string)  Field ID whose value is the post ID. Or use post_id (static).
 *   post_id         (int)     Static post ID (overridden by post_id_field).
 *   taxonomy        (string)  Taxonomy slug, e.g. 'category', 'post_tag', 'custom_tax'.
 *   terms_field     (string)  Field ID whose value contains the term name(s)/ID(s).
 *   terms           (array)   Static list of term names/IDs (used when terms_field is empty).
 *   mode            (string)  'replace' (default) | 'append' | 'remove'.
 *   create_terms    (bool)    Whether to create a term that does not yet exist.
 */
class CLEFA_Taxonomy_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$taxonomy = sanitize_key( $action_config['taxonomy'] ?? '' );

		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return array( 'success' => false, 'message' => "Taxonomy '{$taxonomy}' does not exist." );
		}

		// Resolve post ID
		$post_id = 0;
		$pid_field = $action_config['post_id_field'] ?? '';
		if ( $pid_field && isset( $data[ $pid_field ] ) ) {
			$post_id = absint( $data[ $pid_field ] );
		} elseif ( ! empty( $action_config['post_id'] ) ) {
			$post_id = absint( $this->resolve_token( (string) $action_config['post_id'], $data, $form_config ) );
		}

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return array( 'success' => false, 'message' => "Post ID '{$post_id}' is invalid." );
		}

		// Permission check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'success' => false, 'message' => 'Permission denied to edit this post.' );
		}

		// Resolve terms list
		$terms = array();
		$terms_field = $action_config['terms_field'] ?? '';
		if ( $terms_field && isset( $data[ $terms_field ] ) ) {
			$raw = $data[ $terms_field ];
			$terms = is_array( $raw ) ? $raw : array_map( 'trim', explode( ',', (string) $raw ) );
		} elseif ( ! empty( $action_config['terms'] ) ) {
			$terms = (array) $action_config['terms'];
		}

		$terms = array_filter( array_map( 'sanitize_text_field', $terms ) );

		if ( empty( $terms ) ) {
			return array( 'success' => false, 'message' => 'No terms provided.' );
		}

		// Optionally create missing terms
		$create = ! empty( $action_config['create_terms'] );
		$term_ids = array();

		foreach ( $terms as $term ) {
			$term_val = is_numeric( $term ) ? (int) $term : $term;
			if ( is_int( $term_val ) ) {
				if ( term_exists( $term_val, $taxonomy ) ) {
					$term_ids[] = $term_val;
				}
				continue;
			}

			$existing = get_term_by( 'name', $term, $taxonomy );
			if ( $existing ) {
				$term_ids[] = $existing->term_id;
			} elseif ( $create ) {
				$new = wp_insert_term( $term, $taxonomy );
				if ( ! is_wp_error( $new ) ) {
					$term_ids[] = $new['term_id'];
				}
			}
		}

		if ( empty( $term_ids ) ) {
			return array( 'success' => false, 'message' => 'No valid terms resolved.' );
		}

		$mode   = $action_config['mode'] ?? 'replace';
		$append = ( 'append' === $mode );

		if ( 'remove' === $mode ) {
			$result = wp_remove_object_terms( $post_id, $term_ids, $taxonomy );
		} else {
			$result = wp_set_object_terms( $post_id, $term_ids, $taxonomy, $append );
		}

		if ( is_wp_error( $result ) ) {
			return array( 'success' => false, 'message' => $result->get_error_message() );
		}

		do_action( 'clefa_after_taxonomy_action', $post_id, $taxonomy, $term_ids, $mode, $data, $form_config );

		return array(
			'success'  => true,
			'post_id'  => $post_id,
			'taxonomy' => $taxonomy,
			'term_ids' => $term_ids,
			'mode'     => $mode,
		);
	}
}
