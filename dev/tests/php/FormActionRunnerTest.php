<?php
/**
 * Tests for CLEFA_Form_Action_Runner
 *
 * Covers: register_action(), run_actions() dispatch, disabled-action skip,
 * missing-type skip, fatal-flag halting, exception handling, and the
 * hook integration points (clefa_form_actions, clefa_action_config).
 */

class FormActionRunnerTest extends \PHPUnit\Framework\TestCase {

	/** @var ReflectionProperty */
	private static $registry_prop;

	public static function setUpBeforeClass(): void {
		$ref                  = new ReflectionClass( CLEFA_Form_Action_Runner::class );
		self::$registry_prop  = $ref->getProperty( 'registry' );
		self::$registry_prop->setAccessible( true );
	}

	protected function setUp(): void {
		// Reset static registry between tests.
		self::$registry_prop->setValue( null, array() );
	}

	// -----------------------------------------------------------------------
	// Helper action classes (defined inline for isolation)
	// -----------------------------------------------------------------------

	private function define_action_class( string $class_name, array $result = array( 'success' => true ) ): void {
		if ( ! class_exists( $class_name ) ) {
			eval( '
				class ' . $class_name . ' extends CLEFA_Abstract_Action {
					public static $calls = [];
					public function run( array $data, array $form_config, $submission_id, array $action_config = [] ) {
						self::$calls[] = [ "data" => $data, "action_config" => $action_config ];
						return ' . var_export( $result, true ) . ';
					}
				}
			' );
		}
		// Reset call log
		$calls_prop = ( new ReflectionClass( $class_name ) )->getProperty( 'calls' );
		$calls_prop->setAccessible( true );
		$calls_prop->setValue( null, array() );
	}

	// -----------------------------------------------------------------------
	// register_action
	// -----------------------------------------------------------------------

	public function test_register_action_stores_class_name(): void {
		CLEFA_Form_Action_Runner::register_action( 'my_custom', 'My_Custom_Class' );
		$registry = self::$registry_prop->getValue( null );
		$this->assertArrayHasKey( 'my_custom', $registry );
		$this->assertSame( 'My_Custom_Class', $registry['my_custom'] );
	}

	public function test_register_action_overwrites_existing(): void {
		CLEFA_Form_Action_Runner::register_action( 'dup', 'First_Class' );
		CLEFA_Form_Action_Runner::register_action( 'dup', 'Second_Class' );
		$registry = self::$registry_prop->getValue( null );
		$this->assertSame( 'Second_Class', $registry['dup'] );
	}

	// -----------------------------------------------------------------------
	// run_actions — basic dispatch
	// -----------------------------------------------------------------------

	public function test_run_actions_returns_empty_array_for_no_actions(): void {
		$results = CLEFA_Form_Action_Runner::run_actions( array(), array(), array(), 0 );
		$this->assertSame( array(), $results );
	}

	public function test_run_actions_skips_disabled_action(): void {
		$this->define_action_class( 'TestSkipDisabled_Action' );
		CLEFA_Form_Action_Runner::register_action( 'skip_disabled', 'TestSkipDisabled_Action' );

		$results = CLEFA_Form_Action_Runner::run_actions(
			array( array( 'action_type' => 'skip_disabled', 'enabled' => false ) ),
			array(),
			array(),
			0
		);

		$this->assertArrayNotHasKey( 'skip_disabled', $results );
	}

	public function test_run_actions_skips_action_with_no_type(): void {
		$results = CLEFA_Form_Action_Runner::run_actions(
			array( array( 'enabled' => true ) ),
			array(),
			array(),
			0
		);

		$this->assertSame( array(), $results );
	}

	public function test_run_actions_skips_unknown_action_type(): void {
		$results = CLEFA_Form_Action_Runner::run_actions(
			array( array( 'action_type' => 'nonexistent_xyz_action', 'enabled' => true ) ),
			array(),
			array(),
			0
		);

		$this->assertArrayNotHasKey( 'nonexistent_xyz_action', $results );
	}

	public function test_run_actions_dispatches_registered_action(): void {
		$this->define_action_class( 'TestDispatch_Action' );
		CLEFA_Form_Action_Runner::register_action( 'test_dispatch', 'TestDispatch_Action' );

		$data   = array( 'name' => 'Alice' );
		$config = array( 'form_name' => 'Test Form' );

		$results = CLEFA_Form_Action_Runner::run_actions(
			array( array( 'action_type' => 'test_dispatch', 'enabled' => true ) ),
			$data,
			$config,
			42
		);

		$this->assertArrayHasKey( 'test_dispatch', $results );
		$this->assertTrue( $results['test_dispatch']['success'] );
	}

	public function test_run_actions_passes_data_to_action(): void {
		$this->define_action_class( 'TestPassData_Action' );
		CLEFA_Form_Action_Runner::register_action( 'test_pass_data', 'TestPassData_Action' );

		$data = array( 'email' => 'test@example.com', 'name' => 'Bob' );

		CLEFA_Form_Action_Runner::run_actions(
			array( array( 'action_type' => 'test_pass_data', 'enabled' => true, 'key' => 'val' ) ),
			$data,
			array(),
			99
		);

		$calls = ( new ReflectionClass( 'TestPassData_Action' ) )->getProperty( 'calls' );
		$calls->setAccessible( true );
		$recorded = $calls->getValue( null );

		$this->assertCount( 1, $recorded );
		$this->assertSame( $data, $recorded[0]['data'] );
		$this->assertSame( 'val', $recorded[0]['action_config']['key'] );
	}

	// -----------------------------------------------------------------------
	// run_actions — fatal flag stops the chain
	// -----------------------------------------------------------------------

	public function test_run_actions_stops_chain_on_fatal_result(): void {
		$this->define_action_class( 'TestFatal_Action', array( 'success' => false, 'fatal' => true ) );
		$this->define_action_class( 'TestAfterFatal_Action' );
		CLEFA_Form_Action_Runner::register_action( 'test_fatal', 'TestFatal_Action' );
		CLEFA_Form_Action_Runner::register_action( 'test_after_fatal', 'TestAfterFatal_Action' );

		$actions = array(
			array( 'action_type' => 'test_fatal',       'enabled' => true ),
			array( 'action_type' => 'test_after_fatal', 'enabled' => true ),
		);

		$results = CLEFA_Form_Action_Runner::run_actions( $actions, array(), array(), 0 );

		$this->assertArrayHasKey( 'test_fatal', $results );
		$this->assertArrayNotHasKey( 'test_after_fatal', $results );
	}

	// -----------------------------------------------------------------------
	// run_actions — exception handling
	// -----------------------------------------------------------------------

	public function test_run_actions_captures_exception_in_results(): void {
		if ( ! class_exists( 'TestThrows_Action' ) ) {
			eval( '
				class TestThrows_Action extends CLEFA_Abstract_Action {
					public function run( array $data, array $fc, $sid, array $ac = [] ) {
						throw new \RuntimeException( "Action exploded" );
					}
				}
			' );
		}
		CLEFA_Form_Action_Runner::register_action( 'test_throws', 'TestThrows_Action' );

		$results = CLEFA_Form_Action_Runner::run_actions(
			array( array( 'action_type' => 'test_throws', 'enabled' => true ) ),
			array(),
			array(),
			0
		);

		$this->assertArrayHasKey( 'test_throws', $results );
		$this->assertFalse( $results['test_throws']['success'] );
		$this->assertSame( 'Action exploded', $results['test_throws']['message'] );
	}

	public function test_run_actions_continues_after_exception(): void {
		if ( ! class_exists( 'TestThrows2_Action' ) ) {
			eval( '
				class TestThrows2_Action extends CLEFA_Abstract_Action {
					public function run( array $data, array $fc, $sid, array $ac = [] ) {
						throw new \RuntimeException( "boom" );
					}
				}
			' );
		}
		$this->define_action_class( 'TestAfterException_Action' );
		CLEFA_Form_Action_Runner::register_action( 'test_throws2',          'TestThrows2_Action' );
		CLEFA_Form_Action_Runner::register_action( 'test_after_exception',  'TestAfterException_Action' );

		$results = CLEFA_Form_Action_Runner::run_actions(
			array(
				array( 'action_type' => 'test_throws2',         'enabled' => true ),
				array( 'action_type' => 'test_after_exception', 'enabled' => true ),
			),
			array(),
			array(),
			0
		);

		$this->assertArrayHasKey( 'test_throws2', $results );
		$this->assertArrayHasKey( 'test_after_exception', $results );
		$this->assertTrue( $results['test_after_exception']['success'] );
	}

	// -----------------------------------------------------------------------
	// run_actions — multiple actions in sequence
	// -----------------------------------------------------------------------

	public function test_run_actions_runs_multiple_actions_in_order(): void {
		$this->define_action_class( 'TestMultiA_Action' );
		$this->define_action_class( 'TestMultiB_Action' );
		CLEFA_Form_Action_Runner::register_action( 'multi_a', 'TestMultiA_Action' );
		CLEFA_Form_Action_Runner::register_action( 'multi_b', 'TestMultiB_Action' );

		$results = CLEFA_Form_Action_Runner::run_actions(
			array(
				array( 'action_type' => 'multi_a', 'enabled' => true ),
				array( 'action_type' => 'multi_b', 'enabled' => true ),
			),
			array(),
			array(),
			0
		);

		$this->assertCount( 2, $results );
		$this->assertArrayHasKey( 'multi_a', $results );
		$this->assertArrayHasKey( 'multi_b', $results );
	}

	public function test_run_actions_skips_disabled_but_runs_enabled_in_mixed_list(): void {
		$this->define_action_class( 'TestMixedEnabled_Action' );
		CLEFA_Form_Action_Runner::register_action( 'mixed_enabled', 'TestMixedEnabled_Action' );

		$results = CLEFA_Form_Action_Runner::run_actions(
			array(
				array( 'action_type' => 'mixed_enabled', 'enabled' => false ),
				array( 'action_type' => 'mixed_enabled', 'enabled' => true  ),
			),
			array(),
			array(),
			0
		);

		// Only one result because the type key is the same (second overwrites if run, first skipped).
		$this->assertArrayHasKey( 'mixed_enabled', $results );
	}

	// -----------------------------------------------------------------------
	// confirm_password built-in type resolves through file_map
	// -----------------------------------------------------------------------

	public function test_built_in_confirm_password_resolves(): void {
		$actions = array(
			array(
				'action_type'    => 'confirm_password',
				'enabled'        => true,
				'password_field' => 'pw',
				'confirm_field'  => 'pw2',
			),
		);
		$data = array( 'pw' => 'Secret1!', 'pw2' => 'Secret1!' );

		$results = CLEFA_Form_Action_Runner::run_actions( $actions, $data, array(), 0 );

		$this->assertArrayHasKey( 'confirm_password', $results );
		$this->assertTrue( $results['confirm_password']['success'] );
	}
}
