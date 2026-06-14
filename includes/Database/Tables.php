<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'CLEFA_Tables' ) ) {
	return;
}

class CLEFA_Tables {

	public static function create() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = array();

		$sql[] = "CREATE TABLE {$wpdb->prefix}clefa_forms (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_uuid VARCHAR(36) NOT NULL,
			form_name VARCHAR(255) NOT NULL,
			form_type VARCHAR(50) NOT NULL DEFAULT 'standard',
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			description TEXT DEFAULT NULL,
			config_json LONGTEXT DEFAULT NULL,
			normalized_config_json LONGTEXT DEFAULT NULL,
			feature_map_json TEXT DEFAULT NULL,
			version INT(11) NOT NULL DEFAULT 1,
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			updated_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY form_uuid (form_uuid),
			KEY status (status),
			KEY form_type (form_type)
		) $charset;";

		$sql[] = "CREATE TABLE {$wpdb->prefix}clefa_form_versions (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT(20) UNSIGNED NOT NULL,
			version INT(11) NOT NULL,
			config_json LONGTEXT DEFAULT NULL,
			normalized_config_json LONGTEXT DEFAULT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY version (version)
		) $charset;";

		$sql[] = "CREATE TABLE {$wpdb->prefix}clefa_submissions (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT(20) UNSIGNED NOT NULL,
			form_uuid VARCHAR(36) NOT NULL,
			form_instance_id VARCHAR(64) NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'complete',
			source_url TEXT DEFAULT NULL,
			ip_hash VARCHAR(64) DEFAULT NULL,
			user_agent_hash VARCHAR(64) DEFAULT NULL,
			submitted_data_json LONGTEXT DEFAULT NULL,
			sanitized_data_json LONGTEXT DEFAULT NULL,
			validation_result_json TEXT DEFAULT NULL,
			action_results_json LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$wpdb->prefix}clefa_uploads (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT(20) UNSIGNED NOT NULL,
			form_instance_id VARCHAR(64) NOT NULL,
			submission_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			field_id VARCHAR(64) NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			temp_token VARCHAR(64) NOT NULL,
			original_filename VARCHAR(255) NOT NULL,
			file_path VARCHAR(500) DEFAULT NULL,
			file_url VARCHAR(500) DEFAULT NULL,
			mime_type VARCHAR(100) NOT NULL,
			file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			upload_status VARCHAR(20) NOT NULL DEFAULT 'temp',
			is_committed TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			committed_at DATETIME DEFAULT NULL,
			expires_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY temp_token (temp_token),
			KEY form_id (form_id),
			KEY submission_id (submission_id),
			KEY user_id (user_id),
			KEY is_committed (is_committed),
			KEY expires_at (expires_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$wpdb->prefix}clefa_test_logs (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT(20) UNSIGNED NOT NULL,
			test_group_id VARCHAR(64) NOT NULL,
			test_name VARCHAR(255) NOT NULL,
			expected_result_json TEXT DEFAULT NULL,
			actual_result_json TEXT DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_records_json TEXT DEFAULT NULL,
			cleanup_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY test_group_id (test_group_id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$wpdb->prefix}clefa_audit_logs (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			event_type VARCHAR(100) NOT NULL,
			event_context_json TEXT DEFAULT NULL,
			ip_address VARCHAR(45) DEFAULT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY event_type (event_type),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) $charset;";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/** Per-request in-memory cache for form rows. Keyed by form_id. */
	private static $form_cache = array();

	public static function get_form( $form_id ) {
		$form_id = absint( $form_id );
		if ( isset( self::$form_cache[ $form_id ] ) ) {
			return self::$form_cache[ $form_id ];
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}clefa_forms WHERE id = %d",
				$form_id
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		if ( ! empty( $row['config_json'] ) ) {
			$row['config'] = json_decode( $row['config_json'], true );
		}
		if ( ! empty( $row['feature_map_json'] ) ) {
			$row['feature_map'] = json_decode( $row['feature_map_json'], true );
		}
		self::$form_cache[ $form_id ] = $row;
		return $row;
	}

	/** Invalidate the in-memory cache for a specific form (call after saving). */
	public static function invalidate_form_cache( $form_id ) {
		unset( self::$form_cache[ absint( $form_id ) ] );
	}

	public static function get_forms( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'status'   => '',
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'updated_at',
			'order'    => 'DESC',
		);
		$args    = wp_parse_args( $args, $defaults );
		$where   = '';
		$params  = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		$offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
		$order  = in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? $args['order'] : 'DESC';
		$by     = in_array( $args['orderby'], array( 'id', 'form_name', 'created_at', 'updated_at', 'status' ), true ) ? $args['orderby'] : 'updated_at';

		$query = "SELECT id, form_uuid, form_name, form_type, status, description, version, feature_map_json, created_by, created_at, updated_at FROM {$wpdb->prefix}clefa_forms WHERE 1=1 $where ORDER BY $by $order LIMIT %d OFFSET %d";

		$params[] = absint( $args['per_page'] );
		$params[] = $offset;

		if ( $params ) {
			return $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	public static function count_forms( $status = '' ) {
		global $wpdb;
		if ( $status ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}clefa_forms WHERE status = %s",
					$status
				)
			);
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}clefa_forms" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function get_submissions( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'form_id'  => 0,
			'per_page' => 20,
			'page'     => 1,
		);
		$args   = wp_parse_args( $args, $defaults );
		$where  = '';
		$params = array();

		if ( ! empty( $args['form_id'] ) ) {
			$where   .= ' AND s.form_id = %d';
			$params[] = absint( $args['form_id'] );
		}

		$offset  = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
		$params[] = absint( $args['per_page'] );
		$params[] = $offset;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, f.form_name FROM {$wpdb->prefix}clefa_submissions s LEFT JOIN {$wpdb->prefix}clefa_forms f ON s.form_id = f.id WHERE 1=1 $where ORDER BY s.created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$params
			),
			ARRAY_A
		);
	}

	public static function count_submissions( $form_id = 0 ) {
		global $wpdb;
		if ( $form_id ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}clefa_submissions WHERE form_id = %d",
					absint( $form_id )
				)
			);
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}clefa_submissions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
