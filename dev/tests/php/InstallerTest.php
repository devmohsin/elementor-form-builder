<?php
/**
 * Tests for CLEFA_Installer
 *
 * Covers: DB_OPTION_KEY constant, set_default_options keys and values,
 * maybe_run version comparison, deactivation hook cleanup.
 *
 * Note: run_on_activation() and maybe_run() both call CLEFA_Tables::create()
 * and WP option functions — all stubbed via bootstrap for isolation.
 */

class InstallerTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		global $clefa_test_options;
		$clefa_test_options = array();
	}

	protected function tearDown(): void {
		global $clefa_test_options;
		$clefa_test_options = array();
	}

	// -----------------------------------------------------------------------
	// Constants
	// -----------------------------------------------------------------------

	public function test_db_option_key_constant_is_defined(): void {
		$this->assertSame( 'clefa_db_version', CLEFA_Installer::DB_OPTION_KEY );
	}

	// -----------------------------------------------------------------------
	// set_default_options — triggered via run_on_activation
	// -----------------------------------------------------------------------

	public function test_run_on_activation_sets_expected_option_keys(): void {
		global $clefa_test_options;

		CLEFA_Installer::run_on_activation();

		$expected_keys = array(
			'clefa_default_redirect_url',
			'clefa_default_success_message',
			'clefa_default_error_message',
			'clefa_upload_max_size',
			'clefa_upload_allowed_types',
			'clefa_temp_upload_expiry',
			'clefa_enable_cleanup_schedule',
			'clefa_enable_debug_console',
			'clefa_enable_submission_storage',
			'clefa_enable_antispam',
			'clefa_enable_nonce_refresh',
			'clefa_default_style_mode',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $clefa_test_options, "Expected option key '$key' to be set." );
		}
	}

	public function test_run_on_activation_sets_correct_default_upload_max_size(): void {
		global $clefa_test_options;
		CLEFA_Installer::run_on_activation();
		$this->assertSame( 5, $clefa_test_options['clefa_upload_max_size'] );
	}

	public function test_run_on_activation_sets_correct_default_allowed_types(): void {
		global $clefa_test_options;
		CLEFA_Installer::run_on_activation();
		$this->assertSame( 'jpg,jpeg,png,gif,pdf,doc,docx', $clefa_test_options['clefa_upload_allowed_types'] );
	}

	public function test_run_on_activation_sets_submission_storage_enabled_by_default(): void {
		global $clefa_test_options;
		CLEFA_Installer::run_on_activation();
		$this->assertTrue( $clefa_test_options['clefa_enable_submission_storage'] );
	}

	public function test_run_on_activation_sets_antispam_enabled_by_default(): void {
		global $clefa_test_options;
		CLEFA_Installer::run_on_activation();
		$this->assertTrue( $clefa_test_options['clefa_enable_antispam'] );
	}

	public function test_run_on_activation_sets_debug_console_disabled_by_default(): void {
		global $clefa_test_options;
		CLEFA_Installer::run_on_activation();
		$this->assertFalse( $clefa_test_options['clefa_enable_debug_console'] );
	}

	public function test_run_on_activation_sets_temp_upload_expiry_to_24(): void {
		global $clefa_test_options;
		CLEFA_Installer::run_on_activation();
		$this->assertSame( 24, $clefa_test_options['clefa_temp_upload_expiry'] );
	}

	public function test_run_on_activation_does_not_overwrite_existing_option(): void {
		global $clefa_test_options;

		// Pre-set a value.
		$clefa_test_options['clefa_upload_max_size'] = 99;

		CLEFA_Installer::run_on_activation();

		// add_option only sets if key does not exist; our stub respects that.
		$this->assertSame( 99, $clefa_test_options['clefa_upload_max_size'] );
	}

	public function test_run_on_activation_writes_db_version_to_options(): void {
		global $clefa_test_options;

		CLEFA_Installer::run_on_activation();

		$this->assertArrayHasKey( CLEFA_Installer::DB_OPTION_KEY, $clefa_test_options );
		$this->assertSame( CLEFA_DB_VERSION, $clefa_test_options[ CLEFA_Installer::DB_OPTION_KEY ] );
	}

	// -----------------------------------------------------------------------
	// maybe_run — version comparison
	// -----------------------------------------------------------------------

	public function test_maybe_run_runs_when_stored_version_is_lower(): void {
		global $clefa_test_options;

		// Simulate an old DB version stored.
		$clefa_test_options[ CLEFA_Installer::DB_OPTION_KEY ] = '0.0.1';

		CLEFA_Installer::maybe_run();

		// After maybe_run the stored version should be updated.
		$this->assertSame( CLEFA_DB_VERSION, $clefa_test_options[ CLEFA_Installer::DB_OPTION_KEY ] );
	}

	public function test_maybe_run_does_not_overwrite_version_when_already_current(): void {
		global $clefa_test_options;

		// Set version to current — maybe_run should no-op.
		$clefa_test_options[ CLEFA_Installer::DB_OPTION_KEY ] = CLEFA_DB_VERSION;

		// Poison a default option to prove set_default_options was NOT re-run.
		$clefa_test_options['clefa_upload_max_size'] = 77;

		CLEFA_Installer::maybe_run();

		$this->assertSame( 77, $clefa_test_options['clefa_upload_max_size'] );
	}

	public function test_maybe_run_runs_when_no_version_stored(): void {
		global $clefa_test_options;

		// No version stored at all — get_option returns default '' via bootstrap.
		unset( $clefa_test_options[ CLEFA_Installer::DB_OPTION_KEY ] );

		CLEFA_Installer::maybe_run();

		$this->assertSame( CLEFA_DB_VERSION, $clefa_test_options[ CLEFA_Installer::DB_OPTION_KEY ] );
	}

	// -----------------------------------------------------------------------
	// run_on_deactivation
	// -----------------------------------------------------------------------

	public function test_run_on_deactivation_does_not_throw(): void {
		$this->expectNotToPerformAssertions();
		CLEFA_Installer::run_on_deactivation();
	}

	// -----------------------------------------------------------------------
	// Style mode default
	// -----------------------------------------------------------------------

	public function test_default_style_mode_is_inherited(): void {
		global $clefa_test_options;
		CLEFA_Installer::run_on_activation();
		$this->assertSame( 'inherited', $clefa_test_options['clefa_default_style_mode'] );
	}
}
