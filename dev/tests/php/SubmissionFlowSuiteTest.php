<?php
/**
 * Full submission flow tests — every catalog scenario runs through handle().
 */

class SubmissionFlowSuiteTest extends PHPUnit\Framework\TestCase {

	private CLEFA_Submission_Flow_Test_Runner $runner;

	protected function setUp(): void {
		clefa_test_reset_action_stores();
		wp_set_current_user( 0 );
		CLEFA_Tables::$mock_form           = null;
		CLEFA_Settings_Page::$overrides    = array();
		CLEFA_Audit_Log::$last_event       = null;
		CLEFA_Audit_Log::$last_context     = null;
		$this->runner = new CLEFA_Submission_Flow_Test_Runner();
	}

	public static function scenarioProvider(): array {
		$rows = array();
		foreach ( CLEFA_Form_Scenario_Catalog::all() as $form ) {
			foreach ( (array) ( $form['cases'] ?? array() ) as $case ) {
				$key          = 'handle :: ' . $form['id'] . ' :: ' . ( $case['name'] ?? 'case' );
				$rows[ $key ] = array( $form, $case );
			}
		}
		return $rows;
	}

	/** @dataProvider scenarioProvider */
	public function test_submission_handle_flow_scenario( array $form, array $case ): void {
		clefa_test_reset_action_stores();

		if ( ! empty( $case['seed'] ) ) {
			CLEFA_Form_Scenario_Catalog::applySeed( (string) $case['seed'] );
		}

		$result = $this->runner->run_form( $form, $case );
		$this->runner->assert_case_expectations( $case, $result, $this );
	}
}
