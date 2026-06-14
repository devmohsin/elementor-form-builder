<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds predefined test forms from dev/fixtures/forms/*.json
 */
class CLEFA_Test_Form_Seeder {

	/**
	 * @return array{created:array, skipped:array, errors:array}
	 */
	public static function seed_all() {
		require_once CLEFA_PLUGIN_PATH . 'includes/Core/Testing.php';
		CLEFA_Testing::enable();

		$dir = CLEFA_DEV_PATH . 'fixtures/forms/';
		if ( ! is_dir( $dir ) ) {
			return array(
				'created' => array(),
				'skipped' => array(),
				'errors'  => array( 'Fixtures directory not found: dev/fixtures/forms/' ),
			);
		}

		$created = array();
		$skipped = array();
		$errors  = array();

		foreach ( glob( $dir . '*.json' ) as $file ) {
			$result = self::seed_file( $file );
			if ( is_wp_error( $result ) ) {
				$errors[] = basename( $file ) . ': ' . $result->get_error_message();
			} elseif ( ! empty( $result['skipped'] ) ) {
				$skipped[] = $result;
			} else {
				$created[] = $result;
			}
		}

		return compact( 'created', 'skipped', 'errors' );
	}

	/**
	 * @return array|WP_Error
	 */
	public static function seed_file( $file_path ) {
		$raw = file_get_contents( $file_path );
		if ( false === $raw ) {
			return new WP_Error( 'read_failed', 'Could not read fixture file.' );
		}

		$fixture = json_decode( $raw, true );
		if ( ! is_array( $fixture ) ) {
			return new WP_Error( 'invalid_json', 'Invalid JSON in fixture.' );
		}

		$form_name = sanitize_text_field( $fixture['form_name'] ?? basename( $file_path, '.json' ) );
		$slug      = sanitize_title( $fixture['slug'] ?? $form_name );

		// Skip if a test form with this slug already exists
		$existing = self::find_by_slug( $slug );
		if ( $existing ) {
			return array(
				'skipped'  => true,
				'form_id'  => (int) $existing['id'],
				'form_name'=> $existing['form_name'],
				'slug'     => $slug,
			);
		}

		$config = $fixture['config'] ?? array();
		$config['form_name'] = $form_name;
		if ( empty( $config['steps'] ) ) {
			return new WP_Error( 'no_steps', 'Fixture has no steps.' );
		}

		$form_id = self::insert_form( $form_name, $fixture, $config );

		if ( ! $form_id ) {
			return new WP_Error( 'save_failed', 'Could not save form.' );
		}

		return array(
			'created'   => true,
			'form_id'   => (int) $form_id,
			'form_name' => $form_name,
			'slug'      => $slug,
			'file'      => basename( $file_path ),
		);
	}

	private static function find_by_slug( $slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'clefa_forms';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, form_name, config_json FROM {$table} WHERE form_name = %s OR description LIKE %s LIMIT 1",
				$slug,
				'%[TEST]%'
			),
			ARRAY_A
		);
		return $rows[0] ?? null;
	}

	private static function insert_form( $form_name, array $fixture, array $config ) {
		global $wpdb;

		require_once CLEFA_PLUGIN_PATH . 'includes/Builder/Config_Normalizer.php';
		$normalizer  = new CLEFA_Config_Normalizer();
		$normalized  = $normalizer->normalize( $config );
		$feature_map = method_exists( $normalizer, 'generate_feature_map' )
			? $normalizer->generate_feature_map( $normalized )
			: array();

		$wpdb->insert(
			$wpdb->prefix . 'clefa_forms',
			array(
				'form_uuid'              => wp_generate_uuid4(),
				'form_name'              => $form_name,
				'form_type'              => sanitize_key( $fixture['form_type'] ?? 'standard' ),
				'status'                 => 'published',
				'description'            => sanitize_textarea_field( $fixture['description'] ?? '[TEST] Seeded by dev suite' ),
				'config_json'            => wp_json_encode( $config ),
				'normalized_config_json' => wp_json_encode( $normalized ),
				'feature_map_json'       => wp_json_encode( $feature_map ),
				'created_by'             => get_current_user_id(),
				'updated_by'             => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete all forms marked as test / seeded.
	 */
	public static function delete_seeded_forms() {
		global $wpdb;
		$table = $wpdb->prefix . 'clefa_forms';
		return (int) $wpdb->query(
			"DELETE FROM {$table} WHERE description LIKE '%[TEST]%' OR form_name LIKE '[TEST]%'"
		);
	}
}
