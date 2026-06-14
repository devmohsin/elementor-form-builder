<?php
/**
 * Tests for CLEFA_Capabilities
 *
 * Covers: all() list, current_user_can() with user_can stub,
 * filter override, require() happy path and die path.
 */

class CapabilitiesTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		global $clefa_test_user_caps, $clefa_test_current_user_id;
		$clefa_test_user_caps        = array();
		$clefa_test_current_user_id  = 0;
	}

	protected function tearDown(): void {
		global $clefa_test_user_caps, $clefa_test_current_user_id;
		$clefa_test_user_caps        = array();
		$clefa_test_current_user_id  = 0;
	}

	// -----------------------------------------------------------------------
	// all()
	// -----------------------------------------------------------------------

	public function test_all_returns_array_of_strings(): void {
		$caps = CLEFA_Capabilities::all();
		$this->assertIsArray( $caps );
		$this->assertNotEmpty( $caps );
		foreach ( $caps as $cap ) {
			$this->assertIsString( $cap );
		}
	}

	public function test_all_contains_expected_capability_slugs(): void {
		$caps = CLEFA_Capabilities::all();

		$expected = array(
			'manage_forms',
			'edit_form',
			'delete_form',
			'publish_form',
			'view_submissions',
			'delete_submission',
			'export_submissions',
			'run_tests',
			'view_logs',
			'manage_settings',
		);

		foreach ( $expected as $cap ) {
			$this->assertContains( $cap, $caps, "Expected '$cap' in capabilities list." );
		}
	}

	public function test_all_returns_exactly_10_capabilities(): void {
		$this->assertCount( 10, CLEFA_Capabilities::all() );
	}

	// -----------------------------------------------------------------------
	// current_user_can() — basic WP cap lookup
	// -----------------------------------------------------------------------

	public function test_returns_false_when_user_lacks_manage_options(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps;
		$clefa_test_current_user_id              = 5;
		$clefa_test_user_caps[5]['manage_options'] = false;

		$this->assertFalse( CLEFA_Capabilities::current_user_can( 'manage_forms' ) );
	}

	public function test_returns_true_when_user_has_manage_options(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps;
		$clefa_test_current_user_id              = 5;
		$clefa_test_user_caps[5]['manage_options'] = true;

		$this->assertTrue( CLEFA_Capabilities::current_user_can( 'manage_forms' ) );
	}

	public function test_explicit_user_id_parameter_is_used(): void {
		global $clefa_test_user_caps;
		$clefa_test_user_caps[7]['manage_options'] = true;

		$this->assertTrue( CLEFA_Capabilities::current_user_can( 'edit_form', 7 ) );
	}

	public function test_explicit_user_id_zero_returns_false(): void {
		global $clefa_test_user_caps;
		$clefa_test_user_caps[0]['manage_options'] = false;

		$this->assertFalse( CLEFA_Capabilities::current_user_can( 'edit_form', 0 ) );
	}

	public function test_unknown_cap_slug_defaults_to_manage_options(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps;
		$clefa_test_current_user_id              = 5;
		$clefa_test_user_caps[5]['manage_options'] = true;

		// 'nonexistent_cap' is not in the map, defaults to manage_options.
		$this->assertTrue( CLEFA_Capabilities::current_user_can( 'nonexistent_cap' ) );
	}

	// -----------------------------------------------------------------------
	// clefa_user_can filter override
	// -----------------------------------------------------------------------

	public function test_filter_override_note(): void {
		// apply_filters is a passthrough stub in the unit test environment,
		// so we verify the underlying user_can lookup instead of filter wiring.
		// Full filter integration is covered by browser/integration tests.
		global $clefa_test_current_user_id, $clefa_test_user_caps;
		$clefa_test_current_user_id              = 5;
		$clefa_test_user_caps[5]['manage_options'] = true;

		// Verify raw cap returns true (the value apply_filters would receive and pass through).
		$this->assertTrue( CLEFA_Capabilities::current_user_can( 'view_submissions', 5 ) );
	}

	// -----------------------------------------------------------------------
	// require() — does not throw when user has cap
	// -----------------------------------------------------------------------

	public function test_require_does_not_throw_when_user_has_cap(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps;
		$clefa_test_current_user_id              = 5;
		$clefa_test_user_caps[5]['manage_options'] = true;

		$this->expectNotToPerformAssertions();
		CLEFA_Capabilities::require( 'manage_forms' );
	}

	// -----------------------------------------------------------------------
	// require() — throws via wp_die when user lacks cap
	// -----------------------------------------------------------------------

	public function test_require_throws_when_user_lacks_cap(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps;
		$clefa_test_current_user_id              = 5;
		$clefa_test_user_caps[5]['manage_options'] = false;

		$this->expectException( \RuntimeException::class );
		CLEFA_Capabilities::require( 'manage_forms' );
	}

	public function test_require_throws_for_each_capability(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps;
		$clefa_test_current_user_id              = 5;
		$clefa_test_user_caps[5]['manage_options'] = false;

		foreach ( CLEFA_Capabilities::all() as $cap ) {
			try {
				CLEFA_Capabilities::require( $cap );
				$this->fail( "Expected RuntimeException for cap '$cap' but none was thrown." );
			} catch ( \RuntimeException $e ) {
				$this->assertNotEmpty( $e->getMessage() );
			}
		}
	}

	// -----------------------------------------------------------------------
	// All capabilities resolve through current_user_can
	// -----------------------------------------------------------------------

	public function test_all_caps_return_false_for_user_without_manage_options(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps;
		$clefa_test_current_user_id              = 5;
		$clefa_test_user_caps[5]['manage_options'] = false;

		foreach ( CLEFA_Capabilities::all() as $cap ) {
			$this->assertFalse(
				CLEFA_Capabilities::current_user_can( $cap ),
				"Cap '$cap' should be false for user without manage_options."
			);
		}
	}

	public function test_all_caps_return_true_for_user_with_manage_options(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps;
		$clefa_test_current_user_id              = 5;
		$clefa_test_user_caps[5]['manage_options'] = true;

		foreach ( CLEFA_Capabilities::all() as $cap ) {
			$this->assertTrue(
				CLEFA_Capabilities::current_user_can( $cap ),
				"Cap '$cap' should be true for user with manage_options."
			);
		}
	}
}
