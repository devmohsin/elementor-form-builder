<?php
/**
 * REST route: GET /clefa/v1/select2
 *
 * Returns a list of { id, text } results for a Select2 AJAX source.
 *
 * Supported sources:
 *   posts     — WP_Query over a given post_type
 *   taxonomy  — terms from a given taxonomy
 *   users     — WP_User_Query results
 *   products  — WooCommerce products (post_type = product)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Select2_Route {

	const NAMESPACE = 'clefa/v1';

	public function register() {
		register_rest_route( self::NAMESPACE, '/select2', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'source'   => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
					'enum'              => array( 'posts', 'taxonomy', 'users', 'products' ),
				),
				'post_type' => array(
					'type'              => 'string',
					'default'           => 'post',
					'sanitize_callback' => 'sanitize_key',
				),
				'taxonomy'  => array(
					'type'              => 'string',
					'default'           => 'category',
					'sanitize_callback' => 'sanitize_key',
				),
				'search'    => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'page'      => array(
					'type'    => 'integer',
					'default' => 1,
					'minimum' => 1,
				),
				'per_page'  => array(
					'type'    => 'integer',
					'default' => 20,
					'minimum' => 1,
					'maximum' => 100,
				),
				'form_id'   => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'field_id'  => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_key',
				),
			),
		) );
	}

	public function handle( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'codelinden-elementor-form-addon' ), array( 'status' => 403 ) );
		}

		$source   = $request->get_param( 'source' );
		$search   = $request->get_param( 'search' );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$results    = array();
		$more_pages = false;

		switch ( $source ) {
			case 'posts':
			case 'products':
				$post_type = $source === 'products' ? 'product' : $request->get_param( 'post_type' );
				if ( ! in_array( $post_type, get_post_types(), true ) ) {
					return new WP_Error( 'invalid_post_type', __( 'Invalid post type.', 'codelinden-elementor-form-addon' ), array( 'status' => 400 ) );
				}
				$query_args = array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => $per_page + 1,
					'offset'         => $offset,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				);
				if ( $search ) {
					$query_args['s'] = $search;
				}
				$query_args = apply_filters( 'clefa_select2_query_args', $query_args, $source, $request );
				$query      = new WP_Query( $query_args );
				$ids        = $query->posts;
				$more_pages = count( $ids ) > $per_page;
				$ids        = array_slice( $ids, 0, $per_page );
				foreach ( $ids as $id ) {
					$results[] = array(
						'id'   => (string) $id,
						'text' => get_the_title( $id ),
					);
				}
				break;

			case 'taxonomy':
				$taxonomy = $request->get_param( 'taxonomy' );
				if ( ! taxonomy_exists( $taxonomy ) ) {
					return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.', 'codelinden-elementor-form-addon' ), array( 'status' => 400 ) );
				}
				$term_args = array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => $per_page + 1,
					'offset'     => $offset,
					'fields'     => 'id=>name',
				);
				if ( $search ) {
					$term_args['name__like'] = $search;
					$term_args['search']     = $search;
				}
				$term_args = apply_filters( 'clefa_select2_term_args', $term_args, $taxonomy, $request );
				$terms     = get_terms( $term_args );
				if ( is_wp_error( $terms ) ) {
					$terms = array();
				}
				$more_pages = count( $terms ) > $per_page;
				$terms      = array_slice( $terms, 0, $per_page, true );
				foreach ( $terms as $term_id => $term_name ) {
					$results[] = array(
						'id'   => (string) $term_id,
						'text' => (string) $term_name,
					);
				}
				break;

			case 'users':
				if ( ! current_user_can( 'list_users' ) ) {
					return new WP_Error( 'forbidden', __( 'You do not have permission to list users.', 'codelinden-elementor-form-addon' ), array( 'status' => 403 ) );
				}
				$user_args = array(
					'number'  => $per_page + 1,
					'offset'  => $offset,
					'fields'  => array( 'ID', 'display_name' ),
					'orderby' => 'display_name',
					'order'   => 'ASC',
				);
				if ( $search ) {
					$user_args['search']         = '*' . $search . '*';
					$user_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
				}
				$user_args = apply_filters( 'clefa_select2_user_args', $user_args, $request );
				$users     = get_users( $user_args );
				$more_pages = count( $users ) > $per_page;
				$users      = array_slice( $users, 0, $per_page );
				foreach ( $users as $u ) {
					$results[] = array(
						'id'   => (string) $u->ID,
						'text' => $u->display_name,
					);
				}
				break;

			default:
				$results = apply_filters( 'clefa_select2_custom_source', array(), $source, $request );
				break;
		}

		return rest_ensure_response( array(
			'results'    => $results,
			'pagination' => array( 'more' => $more_pages ),
		) );
	}
}
