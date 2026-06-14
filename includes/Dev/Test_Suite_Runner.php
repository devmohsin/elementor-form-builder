<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs PHPUnit synchronously for the WordPress admin dev hub.
 */
class CLEFA_Test_Suite_Runner {

	/**
	 * Run PHPUnit and return output + strict parsed report.
	 *
	 * @return array{success:bool, output:string, parsed:array|null, exit_code:int}
	 */
	public static function run_phpunit_report() {
		$plugin  = CLEFA_PLUGIN_PATH;
		$phpunit = $plugin . 'vendor/bin/phpunit';
		$config  = $plugin . 'dev/phpunit.xml';

		if ( ! file_exists( $phpunit ) ) {
			return array(
				'success'   => false,
				'exit_code' => 127,
				'output'    => 'PHPUnit not installed. Run: composer require --dev phpunit/phpunit',
				'parsed'    => null,
			);
		}

		self::ensure_runs_dir();
		$junit_file = self::get_runs_dir() . 'last-phpunit.xml';

		$php_bin = self::get_php_binary();
		$command = implode( ' ', array(
			self::quote( $php_bin ),
			self::quote( $phpunit ),
			'-c',
			self::quote( $config ),
			'--no-coverage',
			'--display-deprecations',
			'--log-junit',
			self::quote( $junit_file ),
		) );

		$result = self::run_sync( $command );

		require_once CLEFA_PLUGIN_PATH . 'includes/Dev/PhpUnit_Result_Parser.php';
		$parsed = CLEFA_PhpUnit_Result_Parser::parse(
			$junit_file,
			$result['output'],
			CLEFA_PhpUnit_Result_Parser::get_known_deprecated_tests()
		);

		return array(
			'success'   => ! empty( $parsed['summary']['perfect'] ),
			'exit_code' => $result['exit_code'],
			'output'    => $result['output'],
			'parsed'    => $parsed,
		);
	}

	/**
	 * Run PHPUnit synchronously (CLI use).
	 *
	 * @return array{success:bool, exit_code:int, output:string, command:string}
	 */
	public static function run_phpunit( $with_coverage = false ) {
		$plugin  = CLEFA_PLUGIN_PATH;
		$phpunit = $plugin . 'vendor/bin/phpunit';
		$config  = $plugin . 'dev/phpunit.xml';

		if ( ! file_exists( $phpunit ) ) {
			return array(
				'success'   => false,
				'exit_code' => 127,
				'output'    => 'PHPUnit not installed. Run: composer require --dev phpunit/phpunit',
				'command'   => '',
			);
		}

		$php_bin = self::get_php_binary();
		$args    = array(
			self::quote( $php_bin ),
			self::quote( $phpunit ),
			'-c',
			self::quote( $config ),
		);

		if ( ! $with_coverage ) {
			$args[] = '--no-coverage';
		}

		return self::run_sync( implode( ' ', $args ) );
	}

	/**
	 * Run Jest synchronously (CLI use).
	 *
	 * @return array{success:bool, exit_code:int, output:string, command:string}
	 */
	public static function run_jest() {
		$plugin = rtrim( CLEFA_PLUGIN_PATH, '/\\' );
		$node   = self::find_executable( 'node' );
		$jest   = $plugin . '/node_modules/jest/bin/jest.js';
		$config = $plugin . '/dev/jest.config.js';

		if ( ! $node ) {
			return array(
				'success'   => false,
				'exit_code' => 127,
				'output'    => 'node not found. Install Node.js and run: npm install',
				'command'   => '',
			);
		}

		if ( ! file_exists( $jest ) ) {
			return array(
				'success'   => false,
				'exit_code' => 127,
				'output'    => 'Jest not installed. Run: npm install',
				'command'   => '',
			);
		}

		$command = self::quote( $node ) . ' '
			. self::quote( $jest ) . ' --ci --forceExit --coverage --coverageReporters=text-summary'
			. ' --config ' . self::quote( $config );

		return self::run_sync( $command );
	}

	private static function run_sync( $command ) {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		@set_time_limit( 300 );

		$output = array();
		$code   = 0;

		if ( function_exists( 'exec' ) ) {
			exec( $command . ' 2>&1', $output, $code );
		} else {
			return array(
				'success'   => false,
				'exit_code' => 127,
				'output'    => 'exec() is disabled on this server.',
				'command'   => $command,
			);
		}

		return array(
			'success'   => 0 === $code,
			'exit_code' => $code,
			'output'    => implode( "\n", $output ),
			'command'   => $command,
		);
	}

	public static function get_runs_dir() {
		return CLEFA_DEV_PATH . '.runs/';
	}

	private static function ensure_runs_dir() {
		$dir = self::get_runs_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$index = $dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	private static function get_php_binary() {
		$candidates = array();

		if ( defined( 'PHP_BINARY' ) && PHP_BINARY ) {
			$candidates[] = PHP_BINARY;
		}
		if ( defined( 'PHP_BINDIR' ) && PHP_BINDIR ) {
			$candidates[] = rtrim( PHP_BINDIR, '/\\' ) . ( self::is_windows() ? '\\php.exe' : '/php' );
		}

		$candidates[] = 'php';

		foreach ( $candidates as $bin ) {
			if ( 'php' === $bin ) {
				return $bin;
			}
			if ( self::is_windows() && ! preg_match( '/\.exe$/i', $bin ) ) {
				$bin .= '.exe';
			}
			if ( is_file( $bin ) && stripos( $bin, 'httpd' ) === false && stripos( $bin, 'apache' ) === false ) {
				return $bin;
			}
		}

		return 'php';
	}

	private static function find_executable( $name ) {
		if ( self::is_windows() ) {
			$where = trim( (string) shell_exec( 'where ' . escapeshellarg( $name ) ) );
			if ( $where ) {
				$lines = preg_split( '/\r\n|\n|\r/', $where );
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( $line && is_file( $line ) ) {
						return $line;
					}
				}
			}
		}

		$path = trim( (string) shell_exec( 'command -v ' . escapeshellarg( $name ) ) );
		return $path ?: null;
	}

	private static function quote( $path ) {
		if ( self::is_windows() ) {
			return '"' . str_replace( '"', '""', $path ) . '"';
		}
		return escapeshellarg( $path );
	}

	private static function is_windows() {
		return 'WIN' === strtoupper( substr( PHP_OS, 0, 3 ) );
	}
}
