<?php
/**
 * Tests for CLEFA_Jest_Result_Parser
 *
 * Covers: parsing valid JSON output, failed tests, summary counts,
 * perfect flag, fallback log parsing, and null-return edge cases.
 */

class JestResultParserTest extends \PHPUnit\Framework\TestCase {

	/** Path to a temp dir used for JSON fixture files. */
	private string $tmp_dir;

	protected function setUp(): void {
		$this->tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'clefa_jest_test_' . uniqid();
		mkdir( $this->tmp_dir, 0777, true );
	}

	protected function tearDown(): void {
		// Clean up temp files.
		foreach ( glob( $this->tmp_dir . DIRECTORY_SEPARATOR . '*' ) as $f ) {
			unlink( $f );
		}
		rmdir( $this->tmp_dir );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function write_json( array $data ): string {
		$path = $this->tmp_dir . DIRECTORY_SEPARATOR . 'jest_' . uniqid() . '.json';
		file_put_contents( $path, json_encode( $data ) );
		return $path;
	}

	private function build_jest_output( array $suites ): array {
		return array( 'testResults' => $suites );
	}

	private function build_suite( string $name, array $tests ): array {
		return array(
			'name'             => $name,
			'assertionResults' => $tests,
		);
	}

	private function build_test( string $title, bool $failed = false, array $messages = array() ): array {
		return array(
			'title'           => $title,
			'status'          => $failed ? 'failed' : 'passed',
			'failureMessages' => $messages,
		);
	}

	// -----------------------------------------------------------------------
	// JSON parsing — all passing
	// -----------------------------------------------------------------------

	public function test_parse_valid_json_all_passing_returns_rows(): void {
		$path = $this->write_json( $this->build_jest_output( array(
			$this->build_suite( '/tests/js/MyEngine.test.js', array(
				$this->build_test( 'does the thing' ),
				$this->build_test( 'handles edge case' ),
			) ),
		) ) );

		$result = CLEFA_Jest_Result_Parser::parse( $path );

		$this->assertNotNull( $result );
		$this->assertCount( 2, $result['rows'] );
		$this->assertSame( 'PASS', $result['rows'][0]['status'] );
		$this->assertSame( 'PASS', $result['rows'][1]['status'] );
	}

	public function test_parse_extracts_suite_name_from_path(): void {
		$path = $this->write_json( $this->build_jest_output( array(
			$this->build_suite( '/path/to/ConditionEngine.test.js', array(
				$this->build_test( 'evaluates show' ),
			) ),
		) ) );

		$result = CLEFA_Jest_Result_Parser::parse( $path );

		$this->assertSame( 'ConditionEngine', $result['rows'][0]['suite'] );
	}

	public function test_parse_builds_full_path_from_suite_and_title(): void {
		$path = $this->write_json( $this->build_jest_output( array(
			$this->build_suite( '/path/MyEngine.test.js', array(
				$this->build_test( 'test title here' ),
			) ),
		) ) );

		$result = CLEFA_Jest_Result_Parser::parse( $path );

		$this->assertSame( 'MyEngine › test title here', $result['rows'][0]['full'] );
	}

	// -----------------------------------------------------------------------
	// JSON parsing — failures
	// -----------------------------------------------------------------------

	public function test_parse_failed_test_sets_fail_status(): void {
		$path = $this->write_json( $this->build_jest_output( array(
			$this->build_suite( '/a/Foo.test.js', array(
				$this->build_test( 'broken test', true, array( 'Expected true but got false.' ) ),
			) ),
		) ) );

		$result = CLEFA_Jest_Result_Parser::parse( $path );

		$this->assertSame( 'FAIL', $result['rows'][0]['status'] );
		$this->assertStringContainsString( 'Expected true', $result['rows'][0]['issue'] );
	}

	public function test_parse_failed_test_adds_to_issues(): void {
		$path = $this->write_json( $this->build_jest_output( array(
			$this->build_suite( '/a/Bar.test.js', array(
				$this->build_test( 'fails', true, array( 'some error' ) ),
			) ),
		) ) );

		$result = CLEFA_Jest_Result_Parser::parse( $path );

		$this->assertNotEmpty( $result['issues'] );
		$this->assertSame( 'test', $result['issues'][0]['type'] );
		$this->assertStringContainsString( 'Bar', $result['issues'][0]['source'] );
	}

	// -----------------------------------------------------------------------
	// Summary counts
	// -----------------------------------------------------------------------

	public function test_parse_summary_totals_are_correct(): void {
		$path = $this->write_json( $this->build_jest_output( array(
			$this->build_suite( '/a/Suite.test.js', array(
				$this->build_test( 'pass1' ),
				$this->build_test( 'pass2' ),
				$this->build_test( 'fail1', true ),
			) ),
		) ) );

		$result  = CLEFA_Jest_Result_Parser::parse( $path );
		$summary = $result['summary'];

		$this->assertSame( 3, $summary['total'] );
		$this->assertSame( 2, $summary['passed'] );
		$this->assertSame( 1, $summary['failed'] );
	}

	public function test_parse_perfect_true_when_all_pass_no_issues(): void {
		$path = $this->write_json( $this->build_jest_output( array(
			$this->build_suite( '/a/A.test.js', array(
				$this->build_test( 'ok' ),
			) ),
		) ) );

		$result = CLEFA_Jest_Result_Parser::parse( $path );

		$this->assertTrue( $result['summary']['perfect'] );
	}

	public function test_parse_perfect_false_when_any_fail(): void {
		$path = $this->write_json( $this->build_jest_output( array(
			$this->build_suite( '/a/A.test.js', array(
				$this->build_test( 'fail', true ),
			) ),
		) ) );

		$result = CLEFA_Jest_Result_Parser::parse( $path );

		$this->assertFalse( $result['summary']['perfect'] );
	}

	// -----------------------------------------------------------------------
	// Multiple suites
	// -----------------------------------------------------------------------

	public function test_parse_aggregates_rows_across_multiple_suites(): void {
		$path = $this->write_json( $this->build_jest_output( array(
			$this->build_suite( '/a/A.test.js', array(
				$this->build_test( 'test1' ),
				$this->build_test( 'test2' ),
			) ),
			$this->build_suite( '/a/B.test.js', array(
				$this->build_test( 'test3' ),
			) ),
		) ) );

		$result = CLEFA_Jest_Result_Parser::parse( $path );

		$this->assertSame( 3, $result['summary']['total'] );
		$this->assertSame( 1, $result['rows'][0]['num'] );
		$this->assertSame( 3, $result['rows'][2]['num'] );
	}

	// -----------------------------------------------------------------------
	// Fallback log parsing
	// -----------------------------------------------------------------------

	public function test_parse_falls_back_to_log_when_json_missing(): void {
		$log = "PASS tests/js/ConditionEngine.test.js\nFAIL tests/js/ValidationEngine.test.js\n";

		$result = CLEFA_Jest_Result_Parser::parse( '/nonexistent/path/jest.json', $log );

		$this->assertNotNull( $result );
		$this->assertCount( 2, $result['rows'] );

		$statuses = array_column( $result['rows'], 'status' );
		$this->assertContains( 'PASS', $statuses );
		$this->assertContains( 'FAIL', $statuses );
	}

	public function test_parse_log_extracts_suite_name(): void {
		$log    = "PASS tests/js/StepRouter.test.js\n";
		$result = CLEFA_Jest_Result_Parser::parse( '/nonexistent/jest.json', $log );

		$this->assertSame( 'StepRouter', $result['rows'][0]['suite'] );
	}

	public function test_parse_returns_null_for_missing_json_and_empty_log(): void {
		$result = CLEFA_Jest_Result_Parser::parse( '/nonexistent/jest.json', '' );

		$this->assertNull( $result );
	}

	// -----------------------------------------------------------------------
	// Edge cases
	// -----------------------------------------------------------------------

	public function test_parse_empty_testResults_array_returns_empty_rows(): void {
		$path = $this->write_json( array( 'testResults' => array() ) );

		$result = CLEFA_Jest_Result_Parser::parse( $path );

		$this->assertNotNull( $result );
		$this->assertSame( 0, $result['summary']['total'] );
		$this->assertSame( array(), $result['rows'] );
	}

	public function test_parse_invalid_json_falls_back_to_log(): void {
		$path = $this->tmp_dir . DIRECTORY_SEPARATOR . 'broken.json';
		file_put_contents( $path, 'NOT JSON {{{' );

		$log    = "PASS tests/js/EventDispatcher.test.js\n";
		$result = CLEFA_Jest_Result_Parser::parse( $path, $log );

		$this->assertNotNull( $result );
		$this->assertSame( 'EventDispatcher', $result['rows'][0]['suite'] );
	}

	public function test_parse_log_summary_counts_correctly(): void {
		$log = implode( "\n", array(
			'PASS tests/js/A.test.js',
			'PASS tests/js/B.test.js',
			'FAIL tests/js/C.test.js',
		) );

		$result  = CLEFA_Jest_Result_Parser::parse( '/nonexistent/jest.json', $log );
		$summary = $result['summary'];

		$this->assertSame( 3, $summary['total'] );
		$this->assertSame( 2, $summary['passed'] );
		$this->assertSame( 1, $summary['failed'] );
	}

	public function test_parse_log_perfect_false_when_suite_fails(): void {
		$log    = "FAIL tests/js/Something.test.js\n";
		$result = CLEFA_Jest_Result_Parser::parse( '/nonexistent/jest.json', $log );

		$this->assertFalse( $result['summary']['perfect'] );
	}
}
