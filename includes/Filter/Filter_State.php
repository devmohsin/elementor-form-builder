<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses and normalises the active filter state from a REST request or URL query parameters.
 *
 * Filter values are keyed by section_id and come in as:
 *   clefa_filter[section_id] = scalar | array
 *   clefa_filter[section_id][min] = value
 *   clefa_filter[section_id][max] = value
 *
 * URL params use the same format so that states are shareable.
 */
class CLEFA_Filter_State {

	/** @var array Raw filter values keyed by section_id. */
	private $values = array();

	/** @var int Current page (1-based). */
	private $page = 1;

	/** @var string Order by key (date, title, meta, rand). */
	private $orderby = 'date';

	/** @var string ASC | DESC */
	private $order = 'DESC';

	/**
	 * Build a Filter_State from a REST request.
	 *
	 * @param WP_REST_Request $request
	 * @return static
	 */
	public static function from_request( WP_REST_Request $request ) {
		$instance         = new static();
		$raw              = $request->get_param( 'filter' );
		$instance->values = static::sanitize_values( is_array( $raw ) ? $raw : array() );
		$instance->page   = max( 1, absint( $request->get_param( 'page' ) ?? 1 ) );
		$instance->orderby= sanitize_key( $request->get_param( 'orderby' ) ?? 'date' );
		$instance->order  = strtoupper( $request->get_param( 'order' ) ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
		return $instance;
	}

	/**
	 * Build a Filter_State from URL $_GET params (for server-rendered initial state).
	 *
	 * @return static
	 */
	public static function from_get() {
		$instance = new static();
		// phpcs:ignore WordPress.Security.NonceVerification
		$raw              = isset( $_GET['clefa_filter'] ) ? wp_unslash( $_GET['clefa_filter'] ) : array();
		$instance->values = static::sanitize_values( is_array( $raw ) ? $raw : array() );
		// phpcs:ignore WordPress.Security.NonceVerification
		$instance->page   = max( 1, absint( $_GET['clefa_page'] ?? 1 ) );
		// phpcs:ignore WordPress.Security.NonceVerification
		$instance->orderby= sanitize_key( $_GET['clefa_orderby'] ?? 'date' );
		// phpcs:ignore WordPress.Security.NonceVerification
		$order            = strtoupper( sanitize_key( $_GET['clefa_order'] ?? 'DESC' ) );
		$instance->order  = $order === 'ASC' ? 'ASC' : 'DESC';
		return $instance;
	}

	/** @return array */
	public function get_all() { return $this->values; }

	/** @return mixed|null */
	public function get( $section_id ) { return $this->values[ $section_id ] ?? null; }

	/** @return bool */
	public function is_active( $section_id ) {
		$val = $this->get( $section_id );
		if ( $val === null || $val === '' || $val === array() ) { return false; }
		if ( is_array( $val ) && ! array_filter( $val, 'strlen' ) ) { return false; }
		return true;
	}

	/** @return int */
	public function get_active_count() {
		$count = 0;
		foreach ( array_keys( $this->values ) as $sid ) {
			if ( $this->is_active( $sid ) ) { $count++; }
		}
		return $count;
	}

	/** @return int */
	public function get_page() { return $this->page; }

	/** @return string */
	public function get_orderby() { return $this->orderby; }

	/** @return string */
	public function get_order() { return $this->order; }

	/**
	 * Return a query-string representation for URL sync.
	 *
	 * @return string  e.g. "clefa_filter[color][]=red&clefa_page=2"
	 */
	public function to_query_string() {
		$params = array();
		foreach ( $this->values as $sid => $val ) {
			if ( is_array( $val ) ) {
				foreach ( $val as $k => $v ) {
					$params[] = urlencode( 'clefa_filter[' . $sid . '][' . $k . ']' ) . '=' . urlencode( (string) $v );
				}
			} else {
				$params[] = urlencode( 'clefa_filter[' . $sid . ']' ) . '=' . urlencode( (string) $val );
			}
		}
		if ( $this->page > 1 ) { $params[] = 'clefa_page=' . $this->page; }
		if ( $this->orderby !== 'date' ) { $params[] = 'clefa_orderby=' . urlencode( $this->orderby ); }
		if ( $this->order !== 'DESC' ) { $params[] = 'clefa_order=' . $this->order; }
		return implode( '&', $params );
	}

	/** Deep-sanitize filter values (no HTML, reasonable length). */
	private static function sanitize_values( array $raw ) {
		$out = array();
		foreach ( $raw as $sid => $val ) {
			$sid = sanitize_key( $sid );
			if ( ! $sid ) { continue; }
			if ( is_array( $val ) ) {
				$out[ $sid ] = array_map( 'sanitize_text_field', $val );
			} else {
				$out[ $sid ] = sanitize_text_field( (string) $val );
			}
		}
		return $out;
	}
}
