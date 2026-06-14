<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Testing mode helpers.
 *
 * When CLEFA_TESTING is true, integration runs mark DB records for easy cleanup.
 */
class CLEFA_Testing {

	private static $runtime_enabled = false;

	/**
	 * Enable testing mode for the current request (runtime flag).
	 * PHPUnit bootstrap sets CLEFA_TESTING constant directly.
	 */
	public static function enable() {
		self::$runtime_enabled = true;
	}

	public static function is_active() {
		return self::$runtime_enabled || ( defined( 'CLEFA_TESTING' ) && CLEFA_TESTING );
	}

	/**
	 * Delete all test submissions and pending test logs.
	 *
	 * @return array{submissions:int, logs:int}
	 */
	public static function cleanup_all() {
		global $wpdb;

		$subs_table = $wpdb->prefix . 'clefa_submissions';
		$logs_table = $wpdb->prefix . 'clefa_test_logs';

		$subs = (int) $wpdb->query(
			"DELETE FROM {$subs_table} WHERE status = 'test' OR form_instance_id LIKE 'test-%'"
		);

		$logs = (int) $wpdb->query(
			"DELETE FROM {$logs_table} WHERE cleanup_status IN ('pending', 'cleaned')"
		);

		return array(
			'submissions' => $subs,
			'logs'        => $logs,
		);
	}

	/**
	 * Cleanup a single test group (submissions + log rows).
	 */
	public static function cleanup_group( $group_id ) {
		require_once CLEFA_PLUGIN_PATH . 'includes/Tests/Form_Test_Runner.php';
		$runner = new CLEFA_Form_Test_Runner();
		$runner->cleanup_test_group( sanitize_text_field( $group_id ) );
	}

	/**
	 * Inventory of PHP classes vs test files for the dev coverage panel.
	 *
	 * @return array<int,array{class:string, test_file:string|null, has_test:bool}>
	 */
	public static function get_php_coverage_map() {
		$includes = glob( CLEFA_PLUGIN_PATH . 'includes/**/*.php' );
		$tests    = glob( CLEFA_DEV_PATH . 'tests/php/*Test.php' );
		$test_map = array();

		foreach ( $tests as $test_file ) {
			$base = basename( $test_file, 'Test.php' );
			$test_map[ strtolower( $base ) ] = str_replace( CLEFA_PLUGIN_PATH, '', $test_file );
		}

		$map = array();
		foreach ( $includes as $file ) {
			$class_file = basename( $file, '.php' );
			if ( 'Abstract_Action' === $class_file ) {
				continue;
			}
			$key = strtolower( str_replace( 'CLEFA_', '', $class_file ) );
			$map[] = array(
				'class'     => $class_file,
				'file'      => str_replace( CLEFA_PLUGIN_PATH, '', $file ),
				'test_file' => $test_map[ $key ] ?? null,
				'has_test'  => isset( $test_map[ $key ] ),
			);
		}

		usort( $map, function( $a, $b ) {
			return strcmp( $a['class'], $b['class'] );
		} );

		return $map;
	}

	/**
	 * Inventory of frontend JS modules vs browser unit test files.
	 */
	public static function get_js_coverage_map() {
		$js_files  = glob( CLEFA_PLUGIN_PATH . 'assets/frontend/js/*.js' );
		$tests     = glob( CLEFA_DEV_PATH . 'tests/js/*.test.js' );
		$test_map  = array();

		foreach ( $tests as $test_file ) {
			$base = basename( $test_file, '.test.js' );
			$test_map[ strtolower( $base ) ] = str_replace( CLEFA_PLUGIN_PATH, '', $test_file );
		}

		$skip = array( 'EventDispatcher' );
		$map  = array();

		foreach ( $js_files as $file ) {
			$name = basename( $file, '.js' );
			if ( in_array( $name, $skip, true ) ) {
				continue;
			}
			$key = strtolower( $name );
			$map[] = array(
				'module'    => $name,
				'file'      => str_replace( CLEFA_PLUGIN_PATH, '', $file ),
				'test_file' => $test_map[ $key ] ?? null,
				'has_test'  => isset( $test_map[ $key ] ),
			);
		}

		usort( $map, function( $a, $b ) {
			return strcmp( $a['module'], $b['module'] );
		} );

		return $map;
	}
}
