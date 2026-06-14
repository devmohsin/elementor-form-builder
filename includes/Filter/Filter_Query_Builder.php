<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds WP_Query arguments from a filter widget config and current filter state.
 *
 * Each filter section in the widget config has:
 *  section_id     string
 *  filter_type    string  checkbox | radio | select | range | range_dual | date | search
 *  source_type    string  taxonomy | post_meta | price | date | search | author
 *  source_key     string  taxonomy slug or meta_key
 *  query_compare  string  optional override for meta compare (IN, =, BETWEEN, LIKE, etc.)
 */
class CLEFA_Filter_Query_Builder {

	/** Allowed orderby values mapped to WP_Query equivalents. */
	const ORDERBY_MAP = array(
		'date'       => 'date',
		'title'      => 'title',
		'price'      => 'meta_value_num',
		'rand'       => 'rand',
		'menu_order' => 'menu_order',
		'modified'   => 'modified',
		'comment_count' => 'comment_count',
	);

	/**
	 * Build WP_Query args from filter widget config + state.
	 *
	 * @param array             $widget_config  Elementor widget settings array.
	 * @param CLEFA_Filter_State $state
	 * @return array  WP_Query args (not yet executed).
	 */
	public static function build( array $widget_config, CLEFA_Filter_State $state ) {
		$post_type   = sanitize_key( $widget_config['filter_post_type'] ?? 'post' );
		$per_page    = max( 1, absint( $widget_config['posts_per_page'] ?? get_option( 'posts_per_page', 10 ) ) );
		$orderby_key = $state->get_orderby();
		$orderby_wp  = self::ORDERBY_MAP[ $orderby_key ] ?? 'date';

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $state->get_page(),
			'orderby'        => $orderby_wp,
			'order'          => $state->get_order(),
			'tax_query'      => array( 'relation' => 'AND' ),
			'meta_query'     => array( 'relation' => 'AND' ),
		);

		// Price orderby requires meta key
		if ( 'price' === $orderby_key ) {
			$args['meta_key'] = '_price';
		}

		// Walk filter sections
		$sections = $widget_config['filter_sections'] ?? array();
		foreach ( $sections as $section ) {
			$sid     = $section['section_id'] ?? '';
			$ftype   = $section['filter_type']  ?? 'checkbox';
			$source  = $section['source_type']  ?? 'taxonomy';
			$key     = sanitize_key( $section['source_key'] ?? '' );

			if ( ! $sid || ! $key ) { continue; }
			if ( ! $state->is_active( $sid ) ) { continue; }

			$val = $state->get( $sid );

			switch ( $source ) {
				case 'taxonomy':
					$args['tax_query'][] = self::build_tax_clause( $key, $val, $ftype, $section );
					break;

				case 'post_meta':
					$clause = self::build_meta_clause( $key, $val, $ftype, $section );
					if ( $clause ) { $args['meta_query'][] = $clause; }
					break;

				case 'price':
					$clause = self::build_price_clause( $val, $section );
					if ( $clause ) { $args['meta_query'][] = $clause; }
					break;

				case 'date':
					$date_query = self::build_date_clause( $val );
					if ( $date_query ) { $args['date_query'] = $date_query; }
					break;

				case 'search':
					$args['s'] = sanitize_text_field( is_array( $val ) ? ( $val[0] ?? '' ) : $val );
					break;

				case 'author':
					$author_id = absint( is_array( $val ) ? ( $val[0] ?? 0 ) : $val );
					if ( $author_id ) { $args['author'] = $author_id; }
					break;
			}
		}

		// Strip empty query arrays
		if ( empty( $args['tax_query'] ) || count( $args['tax_query'] ) === 1 ) {
			unset( $args['tax_query'] );
		}
		if ( empty( $args['meta_query'] ) || count( $args['meta_query'] ) === 1 ) {
			unset( $args['meta_query'] );
		}

		return apply_filters( 'clefa_filter_query_args', $args, $widget_config, $state );
	}

	/* ------------------------------------------------------------------ */
	/* Clause builders                                                       */
	/* ------------------------------------------------------------------ */

	private static function build_tax_clause( $taxonomy, $val, $ftype, array $section ) {
		$terms    = is_array( $val ) ? array_map( 'sanitize_text_field', $val ) : array( sanitize_text_field( $val ) );
		$terms    = array_filter( $terms );
		$field    = 'slug';

		// Detect if terms look like IDs
		if ( ! empty( $terms ) && is_numeric( $terms[0] ) ) { $field = 'term_id'; $terms = array_map( 'absint', $terms ); }

		$operator = ( 'radio' === $ftype ) ? 'IN' : ( $section['tax_operator'] ?? 'IN' );
		$operator = in_array( $operator, array( 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS' ), true ) ? $operator : 'IN';

		return array(
			'taxonomy' => $taxonomy,
			'field'    => $field,
			'terms'    => $terms,
			'operator' => $operator,
		);
	}

	private static function build_meta_clause( $meta_key, $val, $ftype, array $section ) {
		if ( in_array( $ftype, array( 'range', 'range_dual' ), true ) || ( is_array( $val ) && isset( $val['min'] ) ) ) {
			$min = floatval( $val['min'] ?? ( is_array( $val ) ? ( $val[0] ?? '' ) : $val ) );
			$max = floatval( $val['max'] ?? ( is_array( $val ) ? ( $val[1] ?? '' ) : $val ) );
			return array(
				'key'     => $meta_key,
				'value'   => array( $min, $max ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		$compare = $section['query_compare'] ?? ( is_array( $val ) ? 'IN' : '=' );
		$compare = in_array( $compare, array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN' ), true ) ? $compare : '=';

		if ( 'IN' === $compare || 'NOT IN' === $compare ) {
			$values = is_array( $val ) ? array_map( 'sanitize_text_field', $val ) : array( sanitize_text_field( $val ) );
			return array( 'key' => $meta_key, 'value' => $values, 'compare' => $compare );
		}

		if ( 'LIKE' === $compare || 'NOT LIKE' === $compare ) {
			return array( 'key' => $meta_key, 'value' => sanitize_text_field( is_array( $val ) ? ( $val[0] ?? '' ) : $val ), 'compare' => $compare );
		}

		return array( 'key' => $meta_key, 'value' => sanitize_text_field( is_array( $val ) ? ( $val[0] ?? '' ) : $val ), 'compare' => $compare );
	}

	private static function build_price_clause( $val, array $section ) {
		$min = floatval( $val['min'] ?? ( is_array( $val ) ? ( $val[0] ?? 0 ) : $val ) );
		$max = floatval( $val['max'] ?? ( is_array( $val ) ? ( $val[1] ?? PHP_INT_MAX ) : PHP_INT_MAX ) );
		if ( $min <= 0 && $max >= PHP_INT_MAX ) { return null; }
		return array(
			'key'     => '_price',
			'value'   => array( $min, $max ),
			'compare' => 'BETWEEN',
			'type'    => 'NUMERIC',
		);
	}

	private static function build_date_clause( $val ) {
		$date_from = sanitize_text_field( is_array( $val ) ? ( $val['from'] ?? ( $val['min'] ?? '' ) ) : '' );
		$date_to   = sanitize_text_field( is_array( $val ) ? ( $val['to']   ?? ( $val['max'] ?? '' ) ) : '' );
		if ( ! $date_from && ! $date_to ) { return null; }

		$clause = array();
		if ( $date_from ) {
			$clause['after'] = array( 'year' => (int) substr( $date_from, 0, 4 ), 'month' => (int) substr( $date_from, 5, 2 ), 'day' => (int) substr( $date_from, 8, 2 ) );
			$clause['inclusive'] = true;
		}
		if ( $date_to ) {
			$clause['before'] = array( 'year' => (int) substr( $date_to, 0, 4 ), 'month' => (int) substr( $date_to, 5, 2 ), 'day' => (int) substr( $date_to, 8, 2 ) );
			$clause['inclusive'] = true;
		}
		return $clause;
	}
}
