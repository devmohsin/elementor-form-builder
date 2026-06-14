<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

/**
 * Action: create or update a WooCommerce product from form data.
 *
 * Requires WooCommerce to be active. Falls back gracefully if WC is not present.
 *
 * Config keys:
 *  product_id_field  string  field_id whose value is an existing product ID (blank = create new)
 *  title_field       string  field to use as product title
 *  description_field string  field for product description
 *  price_field       string  field for regular price
 *  status            string  publish | draft (default draft)
 *  categories_field  string  field containing comma-separated category slugs/IDs
 *  tags_field        string  field containing comma-separated tag slugs
 *  image_field       string  field_id of file upload for product image
 *  sku_field         string  field for SKU
 *  meta_map          array   [ {meta_key, field_id} ] for custom product meta
 */
class CLEFA_WC_Product_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array( 'success' => false, 'error' => 'WooCommerce is not active.' );
		}

		$product_id_field = $action_config['product_id_field'] ?? '';
		$product_id       = $product_id_field ? absint( $data[ $product_id_field ] ?? 0 ) : 0;
		$status           = in_array( $action_config['status'] ?? 'draft', array( 'publish', 'draft', 'private', 'pending' ), true )
			? $action_config['status']
			: 'draft';

		if ( $product_id ) {
			// Editing — verify ownership or capability
			if ( ! current_user_can( 'edit_post', $product_id ) ) {
				return array( 'success' => false, 'error' => 'Permission denied.' );
			}
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return array( 'success' => false, 'error' => 'Product not found.' );
			}
		} else {
			if ( ! current_user_can( 'publish_posts' ) ) {
				return array( 'success' => false, 'error' => 'Permission denied.' );
			}
			$product = new \WC_Product_Simple();
		}

		// Title
		if ( ! empty( $action_config['title_field'] ) ) {
			$title = sanitize_text_field( $this->resolve( '{field:' . $action_config['title_field'] . '}', $data ) );
			if ( $title ) { $product->set_name( $title ); }
		}

		// Description
		if ( ! empty( $action_config['description_field'] ) ) {
			$desc = wp_kses_post( $this->resolve( '{field:' . $action_config['description_field'] . '}', $data ) );
			$product->set_description( $desc );
		}

		// Price
		if ( ! empty( $action_config['price_field'] ) ) {
			$price = floatval( $this->resolve( '{field:' . $action_config['price_field'] . '}', $data ) );
			if ( $price >= 0 ) { $product->set_regular_price( $price ); }
		}

		// SKU
		if ( ! empty( $action_config['sku_field'] ) ) {
			$sku = sanitize_text_field( $this->resolve( '{field:' . $action_config['sku_field'] . '}', $data ) );
			if ( $sku ) { $product->set_sku( $sku ); }
		}

		$product->set_status( $status );
		$product->set_catalog_visibility( 'visible' );

		// Save so we have an ID for meta
		$product_id = $product->save();
		if ( ! $product_id ) {
			return array( 'success' => false, 'error' => 'Failed to save product.' );
		}

		// Categories
		if ( ! empty( $action_config['categories_field'] ) ) {
			$raw_cats = $this->resolve( '{field:' . $action_config['categories_field'] . '}', $data );
			$cat_ids  = $this->resolve_term_ids( $raw_cats, 'product_cat' );
			if ( $cat_ids ) { wp_set_post_terms( $product_id, $cat_ids, 'product_cat' ); }
		}

		// Tags
		if ( ! empty( $action_config['tags_field'] ) ) {
			$raw_tags = $this->resolve( '{field:' . $action_config['tags_field'] . '}', $data );
			$tag_ids  = $this->resolve_term_ids( $raw_tags, 'product_tag' );
			if ( $tag_ids ) { wp_set_post_terms( $product_id, $tag_ids, 'product_tag' ); }
		}

		// Image (from upload field)
		if ( ! empty( $action_config['image_field'] ) ) {
			$attachment_id = absint( $data[ $action_config['image_field'] . '_attachment_id' ] ?? 0 );
			if ( $attachment_id ) { set_post_thumbnail( $product_id, $attachment_id ); }
		}

		// Custom meta map
		if ( ! empty( $action_config['meta_map'] ) && is_array( $action_config['meta_map'] ) ) {
			foreach ( $action_config['meta_map'] as $mapping ) {
				$meta_key = sanitize_key( $mapping['meta_key'] ?? '' );
				$fid      = $mapping['field_id'] ?? '';
				if ( $meta_key && isset( $data[ $fid ] ) ) {
					update_post_meta( $product_id, $meta_key, sanitize_text_field( $data[ $fid ] ) );
				}
			}
		}

		return array(
			'success'    => true,
			'product_id' => $product_id,
		);
	}

	/**
	 * Convert a comma-separated string of term slugs or IDs to an array of term IDs.
	 */
	private function resolve_term_ids( $raw, $taxonomy ) {
		$items = array_map( 'trim', explode( ',', (string) $raw ) );
		$ids   = array();
		foreach ( $items as $item ) {
			if ( ! $item ) { continue; }
			if ( is_numeric( $item ) ) {
				$ids[] = absint( $item );
			} else {
				$term = get_term_by( 'slug', sanitize_key( $item ), $taxonomy );
				if ( $term ) { $ids[] = $term->term_id; }
			}
		}
		return $ids;
	}
}
