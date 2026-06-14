<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Logs_Page {

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'codelinden-elementor-form-addon' ) );
		}

		$per_page   = 30;
		$page       = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$form_id    = absint( $_GET['form_id'] ?? 0 );
		$event_type = sanitize_key( $_GET['event_type'] ?? '' );
		$offset     = ( $page - 1 ) * $per_page;

		global $wpdb;

		$where   = 'WHERE 1=1';
		$params  = array();

		if ( $form_id ) {
			$where    .= ' AND l.form_id = %d';
			$params[]  = $form_id;
		}
		if ( $event_type ) {
			$where    .= ' AND l.event_type = %s';
			$params[]  = $event_type;
		}

		$params_page   = array_merge( $params, array( $per_page, $offset ) );
		$params_count  = $params;

		$query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT l.*, f.form_name FROM {$wpdb->prefix}clefa_audit_logs l LEFT JOIN {$wpdb->prefix}clefa_forms f ON l.form_id = f.id $where ORDER BY l.created_at DESC LIMIT %d OFFSET %d",
			$params_page
		);

		$logs = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$count_query = $params_count
			? $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$wpdb->prefix}clefa_audit_logs l $where",
				$params_count
			)
			: "SELECT COUNT(*) FROM {$wpdb->prefix}clefa_audit_logs l $where"; // phpcs:ignore WordPress.DB.PreparedSQL

		$total      = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total_pages= (int) ceil( $total / $per_page );

		$forms = CLEFA_Tables::get_forms( array( 'per_page' => 200 ) );

		$event_types = $wpdb->get_col( "SELECT DISTINCT event_type FROM {$wpdb->prefix}clefa_audit_logs ORDER BY event_type ASC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$template = CLEFA_TEMPLATE_PATH . 'admin/logs.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
