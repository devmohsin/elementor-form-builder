<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses Jest JSON output into strict pass/fail rows.
 */
class CLEFA_Jest_Result_Parser {

	public static function parse( $json_path, $raw_log = '' ) {
		require_once CLEFA_PLUGIN_PATH . 'includes/Dev/PhpUnit_Result_Parser.php';

		$rows   = array();
		$issues = CLEFA_PhpUnit_Result_Parser::parse_env_issues( $raw_log );

		if ( ! file_exists( $json_path ) ) {
			return self::parse_from_log( $raw_log, $issues );
		}

		$data = json_decode( (string) file_get_contents( $json_path ), true );
		if ( ! is_array( $data ) ) {
			return self::parse_from_log( $raw_log, $issues );
		}

		$num = 0;
		foreach ( $data['testResults'] ?? array() as $suite ) {
			$suite_name = basename( str_replace( '\\', '/', $suite['name'] ?? 'unknown' ), '.test.js' );
			foreach ( $suite['assertionResults'] ?? array() as $test ) {
				++$num;
				$failed = 'failed' === ( $test['status'] ?? '' );
				$rows[] = array(
					'num'    => $num,
					'suite'  => $suite_name,
					'test'   => $test['title'] ?? 'unknown',
					'full'   => $suite_name . ' › ' . ( $test['title'] ?? '' ),
					'status' => $failed ? 'FAIL' : 'PASS',
					'issue'  => $failed ? implode( '; ', $test['failureMessages'] ?? array() ) : '',
				);
				if ( $failed ) {
					$issues[] = array(
						'type'   => 'test',
						'source' => $suite_name . ' › ' . ( $test['title'] ?? '' ),
						'detail' => implode( ' ', $test['failureMessages'] ?? array() ),
					);
				}
			}
		}

		$failed = count( array_filter( $rows, function( $r ) { return 'FAIL' === $r['status']; } ) );
		$total  = count( $rows );

		return array(
			'rows'    => $rows,
			'summary' => array(
				'total'   => $total,
				'passed'  => $total - $failed,
				'failed'  => $failed,
				'perfect' => 0 === $failed && empty( $issues ),
			),
			'issues'  => $issues,
		);
	}

	private static function parse_from_log( $raw_log, array $issues ) {
		$rows = array();
		$num  = 0;

		if ( preg_match_all( '/^(PASS|FAIL)\s+(\S+)/m', $raw_log, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				++$num;
				$file   = basename( $match[2], '.test.js' );
				$failed = 'FAIL' === $match[1];
				$rows[] = array(
					'num'    => $num,
					'suite'  => $file,
					'test'   => '(suite)',
					'full'   => $file,
					'status' => $failed ? 'FAIL' : 'PASS',
					'issue'  => $failed ? 'Suite failed — re-run with JSON output for details' : '',
				);
			}
		}

		if ( empty( $rows ) ) {
			return null;
		}

		$failed = count( array_filter( $rows, function( $r ) { return 'FAIL' === $r['status']; } ) );
		$total  = count( $rows );

		return array(
			'rows'    => $rows,
			'summary' => array(
				'total'   => $total,
				'passed'  => $total - $failed,
				'failed'  => $failed,
				'perfect' => 0 === $failed && empty( $issues ),
			),
			'issues'  => $issues,
		);
	}
}
