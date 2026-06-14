<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses PHPUnit JUnit XML and raw log output into strict pass/fail rows.
 */
class CLEFA_PhpUnit_Result_Parser {

	/**
	 * @return array{
	 *   rows: array<int,array{num:int,suite:string,test:string,full:string,status:string,issue:string}>,
	 *   summary: array{total:int,passed:int,failed:int,perfect:bool},
	 *   issues: array<int,array{type:string,source:string,detail:string}>
	 * }
	 */
	public static function parse( $junit_path, $raw_log = '', array $deprecated_tests = array() ) {
		$rows   = array();
		$issues = self::parse_env_issues( $raw_log );

		if ( file_exists( $junit_path ) ) {
			$xml = simplexml_load_file( $junit_path );
			if ( $xml ) {
				foreach ( $xml->testsuite->testsuite->testsuite as $suite ) {
					self::collect_testcases( $suite, $rows, $deprecated_tests );
				}
			}
		}

		foreach ( $rows as $i => &$row ) {
			$row['num'] = $i + 1;
			if ( 'FAIL' === $row['status'] && $row['issue'] ) {
				$issues[] = array(
					'type'   => 'test',
					'source' => $row['full'],
					'detail' => $row['issue'],
				);
			}
		}
		unset( $row );

		$failed = count( array_filter( $rows, function( $r ) { return 'FAIL' === $r['status']; } ) );
		$total  = count( $rows );

		if ( preg_match( '/Deprecations:\s+(\d+)/', $raw_log, $m ) && (int) $m[1] > 0 ) {
			$issues[] = array(
				'type'   => 'deprecation',
				'source' => 'PHPUnit',
				'detail' => 'Deprecations reported: ' . $m[1],
			);
		}

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

	private static function collect_testcases( SimpleXMLElement $node, array &$rows, array $deprecated ) {
		foreach ( $node->testcase as $tc ) {
			$name  = (string) $tc['name'];
			$class = (string) $tc['class'];
			$base  = preg_replace( '/\s+with data set.*/', '', $name );
			$full  = $class . '::' . $base;
			$fail  = isset( $tc->failure ) || isset( $tc->error );
			$dep   = in_array( $full, $deprecated, true );
			$issue = '';
			if ( $dep ) {
				$issue = 'PHP deprecation triggered (strict mode = fail)';
			} elseif ( $fail ) {
				$issue = trim( (string) ( $tc->failure ?? $tc->error ) );
			}
			$rows[] = array(
				'suite'  => $class,
				'test'   => $name,
				'full'   => $full,
				'status' => ( $fail || $dep ) ? 'FAIL' : 'PASS',
				'issue'  => $issue,
			);
		}
		foreach ( $node->testsuite as $child ) {
			self::collect_testcases( $child, $rows, $deprecated );
		}
	}

	public static function parse_env_issues( $raw_log ) {
		$issues = array();
		if ( ! $raw_log ) {
			return $issues;
		}

		$patterns = array(
			'/PHP Warning:.*pdo_firebird.*/i'     => 'pdo_firebird extension enabled but DLL missing — comment out in php.ini',
			'/PHP Warning:.*curl.*already loaded/i' => 'extension=curl listed twice in php.ini',
			'/PHP Warning:.*date\.timezone.*/i'  => 'date.timezone is empty in php.ini',
			'/Cannot find module \(.*\)/i'        => 'SNMP MIB module missing (harmless noise)',
		);

		foreach ( explode( "\n", $raw_log ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false !== strpos( $line, 'PHPUnit ' ) ) {
				continue;
			}
			if ( preg_match( '/^(OK|Time:|Tests:|\.{3,}|Configuration:|Runtime:)/', $line ) ) {
				continue;
			}
			foreach ( $patterns as $pattern => $hint ) {
				if ( preg_match( $pattern, $line ) ) {
					$issues[] = array(
						'type'   => 'environment',
						'source' => 'PHP/Apache',
						'detail' => $line . ' — ' . $hint,
					);
					break;
				}
			}
		}

		return $issues;
	}

	/**
	 * Known deprecations from PHPUnit 10 + PHP 8.4 (strict = fail).
	 */
	public static function get_known_deprecated_tests() {
		return array();
	}
}
