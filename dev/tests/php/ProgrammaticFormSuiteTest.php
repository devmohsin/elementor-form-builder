<?php
/**
 * Programmatic 100-form edge-case suite.
 *
 * Forms are built in memory (not loaded from JSON fixtures).
 * Each form has pass/fail submission cases with expected validation errors
 * and side-effect verification (posts created, users registered, etc.).
 */

class ProgrammaticFormSuiteTest extends PHPUnit\Framework\TestCase {

	private CLEFA_Programmatic_Form_Test_Runner $runner;

	protected function setUp(): void {
		clefa_test_reset_action_stores();
		wp_set_current_user( 0 );
		$this->runner = new CLEFA_Programmatic_Form_Test_Runner();
	}

	public function test_catalog_contains_one_hundred_forms(): void {
		$this->assertCount( 100, CLEFA_Form_Scenario_Catalog::all() );
	}

	public function test_catalog_has_at_least_one_hundred_fifty_cases(): void {
		$this->assertGreaterThanOrEqual( 150, CLEFA_Form_Scenario_Catalog::totalCases() );
	}

	public static function scenarioProvider(): array {
		$rows = array();
		foreach ( CLEFA_Form_Scenario_Catalog::all() as $form ) {
			foreach ( (array) ( $form['cases'] ?? array() ) as $case ) {
				$key          = $form['id'] . ' :: ' . ( $case['name'] ?? 'case' );
				$rows[ $key ] = array( $form, $case );
			}
		}
		return $rows;
	}

	/** @dataProvider scenarioProvider */
	public function test_programmatic_form_scenario( array $form, array $case ): void {
		clefa_test_reset_action_stores();

		if ( ! empty( $case['seed'] ) ) {
			CLEFA_Form_Scenario_Catalog::applySeed( (string) $case['seed'] );
		}

		$result = $this->runner->run( $form['config'], $case );
		$this->runner->assert_case_expectations( $case, $result, $this );
	}
}
