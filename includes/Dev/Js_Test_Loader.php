<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves dev/tests/js files to the in-browser unit test harness.
 */
class CLEFA_Js_Test_Loader {

	const AJAX_ACTION       = 'clefa_dev_js_test_script';
	const FIXTURE_ACTION    = 'clefa_dev_fixture_html';
	const RUNNER_ACTION     = 'clefa_dev_js_runner';

	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'serve_script' ) );
		add_action( 'wp_ajax_' . self::FIXTURE_ACTION, array( __CLASS__, 'serve_fixture' ) );
		add_action( 'wp_ajax_' . self::RUNNER_ACTION, array( __CLASS__, 'serve_runner' ) );
	}

	public static function can_access() {
		return current_user_can( 'manage_options' )
			&& ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || apply_filters( 'clefa_allow_dev_suite', false ) );
	}

	/**
	 * Frontend JS modules to load before tests (dependency order).
	 *
	 * @return string[]
	 */
	public static function get_module_urls() {
		$frontend_base = CLEFA_ASSET_URL . 'frontend/js/';
		$frontend_files = array(
			'TransitionEngine.js',
			'EventDispatcher.js',
			'ValidationEngine.js',
			'ConditionEngine.js',
			'StepRouter.js',
			'FormEngine.js',
			'FilterEngine.js',
			'LiveCheckManager.js',
			'UploadManager.js',
		);
		$urls = array_map(
			function ( $file ) use ( $frontend_base ) {
				return $frontend_base . $file;
			},
			$frontend_files
		);
		$urls[] = CLEFA_ASSET_URL . 'admin/js/admin-builder.js';
		return $urls;
	}

	const TEST_BUNDLE_FILE = '__CLEFA_test_bundle__.js';

	/**
	 * Test file basenames (dev/tests/js/*.test.js).
	 *
	 * @return string[]
	 */
	public static function get_test_files() {
		$files = glob( CLEFA_DEV_PATH . 'tests/js/*.test.js' );
		if ( ! is_array( $files ) ) {
			return array();
		}
		sort( $files );
		return array_map( 'basename', $files );
	}

	/** Unit tests — minimal / mocked DOM helpers. */
	public static function get_unit_test_files() {
		return array_values(
			array_filter(
				self::get_test_files(),
				function ( $file ) {
					return 'RealDom.integration.test.js' !== $file;
				}
			)
		);
	}

	/** Integration tests — real rendered HTML fixtures. */
	public static function get_integration_test_files() {
		$files = self::get_test_files();
		return in_array( 'RealDom.integration.test.js', $files, true )
			? array( 'RealDom.integration.test.js' )
			: array();
	}

	/**
	 * Approximate JS test case count (test/it blocks in dev/tests/js).
	 */
	public static function count_js_tests() {
		$count = 0;
		foreach ( self::get_test_files() as $file ) {
			$count += self::count_js_tests_in_file( $file );
		}
		return $count;
	}

	public static function count_js_tests_in_file( $basename ) {
		$path = CLEFA_DEV_PATH . 'tests/js/' . $basename;
		if ( ! is_file( $path ) ) {
			return 0;
		}
		$content = (string) file_get_contents( $path );
		return (int) preg_match_all( '/^\s*(?:test|it)\s*\(/m', $content );
	}

	public static function get_test_script_url( $basename ) {
		return add_query_arg(
			array(
				'action' => self::AJAX_ACTION,
				'file'   => $basename,
				'nonce'  => wp_create_nonce( self::AJAX_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	public static function get_fixture_url() {
		return add_query_arg(
			array(
				'action' => self::FIXTURE_ACTION,
				'nonce'  => wp_create_nonce( self::FIXTURE_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	public static function get_runner_url() {
		return add_query_arg(
			array(
				'action' => self::RUNNER_ACTION,
				'nonce'  => wp_create_nonce( self::RUNNER_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Config passed into the self-contained runner iframe.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_test_bundle_url() {
		return add_query_arg(
			array(
				'action' => self::AJAX_ACTION,
				'file'   => self::TEST_BUNDLE_FILE,
				'nonce'  => wp_create_nonce( self::AJAX_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	public static function get_suite_data() {
		$total_js_tests = self::count_js_tests();

		return array(
			'modules'          => self::get_module_urls(),
			'harnessUrl'       => CLEFA_ASSET_URL . 'admin/js/clefa-test-harness.js',
			'formCssUrl'       => CLEFA_ASSET_URL . 'frontend/css/form-engine.css',
			'testBundleUrl'    => self::get_test_bundle_url(),
			'fixtureUrl'       => self::get_fixture_url(),
			'totalJsTests'     => $total_js_tests,
			'jsTestFileCount'  => count( self::get_test_files() ),
			'expectedPhpTests' => 827,
			'clefaFrontend'    => array(
				'restUrl'      => esc_url_raw( rest_url( 'clefa/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'refreshNonce' => false,
				'ajaxUrl'      => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'debugEvents'  => false,
				'i18n'         => array(
					'checking'    => __( 'Checking…', 'codelinden-elementor-form-addon' ),
					'available'   => __( 'Available', 'codelinden-elementor-form-addon' ),
					'unavailable' => __( 'Not available', 'codelinden-elementor-form-addon' ),
				),
			),
			'i18n'             => array(
				'runTests'         => __( 'Run JS Tests', 'codelinden-elementor-form-addon' ),
				'resultsTitle'     => __( 'JavaScript Results (strict)', 'codelinden-elementor-form-addon' ),
				'perfect'          => __( '100% PERFECT PASS', 'codelinden-elementor-form-addon' ),
				'fail'             => __( 'FULL FAIL — issues found', 'codelinden-elementor-form-addon' ),
				'running'          => __( 'Running JavaScript tests…', 'codelinden-elementor-form-addon' ),
				'done'             => __( 'Done.', 'codelinden-elementor-form-addon' ),
				'preparing'        => __( 'Preparing test run…', 'codelinden-elementor-form-addon' ),
				'phasePrepare'     => __( 'Prepare', 'codelinden-elementor-form-addon' ),
				'loadingModules'   => __( 'Loading JS modules…', 'codelinden-elementor-form-addon' ),
				'loadingTests'     => __( 'Loading test bundle…', 'codelinden-elementor-form-addon' ),
				'runningTests'     => __( 'Running tests…', 'codelinden-elementor-form-addon' ),
				'phaseTests'       => __( 'All tests', 'codelinden-elementor-form-addon' ),
				'runnerTitle'      => __( 'JavaScript Test Runner', 'codelinden-elementor-form-addon' ),
				'idleHint'         => __( 'Click Run to execute every JS test. Full pass/fail table below.', 'codelinden-elementor-form-addon' ),
				'suiteScope'       => __( 'Runs one bundled test suite covering every plugin JS module (frontend + admin builder). PHP tests use the PHPUnit button.', 'codelinden-elementor-form-addon' ),
				'colFile'          => __( 'File', 'codelinden-elementor-form-addon' ),
				'colSuite'         => __( 'Suite', 'codelinden-elementor-form-addon' ),
				'colTest'          => __( 'Test', 'codelinden-elementor-form-addon' ),
				'colResult'        => __( 'Result', 'codelinden-elementor-form-addon' ),
				'colIssue'         => __( 'Issue', 'codelinden-elementor-form-addon' ),
			),
		);
	}

	public static function serve_runner() {
		if ( ! self::can_access() ) {
			wp_die( esc_html__( 'Forbidden', 'codelinden-elementor-form-addon' ), '', array( 'response' => 403 ) );
		}

		check_ajax_referer( self::RUNNER_ACTION, 'nonce' );

		$suite_data   = self::get_suite_data();
		$runner_css   = CLEFA_ASSET_URL . 'admin/css/clefa-js-test-runner.css';
		$runner_js    = CLEFA_ASSET_URL . 'admin/js/clefa-js-test-runner.js';
		$form_css     = $suite_data['formCssUrl'];
		$version      = CLEFA_PLUGIN_VERSION;
		$json_flags   = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
		$suite_json   = wp_json_encode( $suite_data, $json_flags );
		$frontend_json = wp_json_encode( $suite_data['clefaFrontend'], $json_flags );

		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

		echo '<!DOCTYPE html><html lang="', esc_attr( get_user_locale() ), '"><head>';
		echo '<meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>', esc_html( $suite_data['i18n']['runnerTitle'] ), '</title>';
		echo '<link rel="stylesheet" href="', esc_url( $form_css ), '?ver=', esc_attr( $version ), '">';
		echo '<link rel="stylesheet" href="', esc_url( $runner_css ), '?ver=', esc_attr( $version ), '">';
		echo '<script>window.clefaJsSuiteData=', $suite_json, ';window.clefaFrontend=', $frontend_json, ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</head><body class="clefa-js-test-runner">';
		include CLEFA_TEMPLATE_PATH . 'admin/js-test-runner-shell.php';
		echo '<script src="', esc_url( $runner_js ), '?ver=', esc_attr( $version ), '"></script>';
		echo '</body></html>';
		exit;
	}

	public static function serve_fixture() {
		if ( ! self::can_access() ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}

		check_ajax_referer( self::FIXTURE_ACTION, 'nonce' );

		require_once CLEFA_PLUGIN_PATH . 'includes/Dev/Fixture_Html_Renderer.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Renderer.php';

		$slug   = sanitize_file_name( wp_unslash( $_GET['slug'] ?? '' ) );
		$result = CLEFA_Fixture_Html_Renderer::render( $slug );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => 'Fixture not found' ), 404 );
		}

		wp_send_json_success( $result );
	}

	public static function serve_script() {
		if ( ! self::can_access() ) {
			status_header( 403 );
			exit;
		}

		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		// Do NOT use sanitize_file_name() — it mangles "*.test.js" into "*-test.js"
		// (WordPress replaces dots before the final extension with hyphens).
		$file = basename( wp_unslash( $_GET['file'] ?? '' ) );

		header( 'Content-Type: application/javascript; charset=utf-8' );

		if ( self::TEST_BUNDLE_FILE === $file ) {
			echo self::build_browser_test_bundle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		$allowed = self::get_test_files();
		if ( ! $file || ! in_array( $file, $allowed, true ) ) {
			status_header( 400 );
			exit;
		}

		$path = CLEFA_DEV_PATH . 'tests/js/' . $file;
		if ( ! is_file( $path ) ) {
			status_header( 404 );
			exit;
		}

		echo self::transform_for_browser( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * One script: all test files transformed and concatenated (single browser load).
	 */
	public static function build_browser_test_bundle() {
		$globals = self::get_browser_globals_js();
		$helpers = self::get_helpers_js();
		$parts   = array(
			"(function(){\n",
			"'use strict';\n",
			"window.CLEFA_TESTING = true;\n",
			$globals,
			$helpers,
		);

		foreach ( self::get_test_files() as $basename ) {
			$path = CLEFA_DEV_PATH . 'tests/js/' . $basename;
			if ( ! is_file( $path ) ) {
				continue;
			}
			$content = self::strip_test_file_for_browser( (string) file_get_contents( $path ) );
			$parts[] = "\n/* ---- " . $basename . " ---- */\n";
			$parts[] = 'window.__clefaCurrentTestFile = ' . wp_json_encode( $basename ) . ";\n";
			$parts[] = $content;
		}

		$parts[] = "\n})();";
		return implode( '', $parts );
	}

	/**
	 * Strip Node requires / wrap body only (shared globals/helpers injected once by bundle).
	 */
	public static function strip_test_file_for_browser( $content ) {
		$content = preg_replace( '/^const \{[^}]+\} = require\(\s*[\'"]@jest\/globals[\'"]\s*\);\s*\r?\n/m', '', $content );
		$content = preg_replace( '/^const path = require\(\s*[\'"]path[\'"]\s*\);\s*\r?\n/m', '', $content );
		$content = preg_replace( '/^const \{ makeForm[^}]+\} = require\(\s*[\'"]\.\/helpers\/dom[\'"]\s*\);\s*\r?\n/m', '', $content );
		$content = preg_replace( '/^const \{ buildFilterWidget \} = require\(\s*[\'"]\.\/helpers\/filter-dom\.js[\'"]\s*\);\s*\r?\n/m', '', $content );
		$content = preg_replace( '/^require\(\s*[\'"][^\'"]*assets\/frontend\/js\/[^\'"]+[\'"]\s*\);\s*\r?\n/m', '', $content );
		$content = preg_replace( '/^require\(\s*[\'"][^\'"]*assets\/admin\/js\/[^\'"]+[\'"]\s*\);\s*\r?\n/m', '', $content );
		$content = preg_replace( '/beforeAll\(\s*\(\)\s*=>\s*\{\s*(?:require\([^;]+;\s*)+\}\s*\);\s*\r?\n/s', '', $content );
		$content = str_replace( "document.body.innerHTML = '';", 'clearTestDom();', $content );
		$content = str_replace( 'document.body.innerHTML = "";', 'clearTestDom();', $content );
		$content = str_replace( 'document.body.innerHTML=\'\';', 'clearTestDom();', $content );
		$content = preg_replace(
			'/document\.body\.innerHTML\s*=/',
			'document.getElementById("clefa-test-mount").innerHTML =',
			$content
		);
		return $content;
	}

	/**
	 * Strip Node/Jest requires and wrap for browser globals.
	 */
	public static function transform_for_browser( $content ) {
		$content = self::strip_test_file_for_browser( $content );
		$helpers = self::get_helpers_js();
		$globals = self::get_browser_globals_js();

		return "(function(){\n"
			. "'use strict';\n"
			. "window.CLEFA_TESTING = true;\n"
			. $globals
			. $helpers
			. $content
			. "\n})();";
	}

	private static function get_browser_globals_js() {
		return "var global = window;\n"
			. "var describe = window.describe;\n"
			. "var test = window.test;\n"
			. "var it = window.it;\n"
			. "var expect = window.expect;\n"
			. "var beforeEach = window.beforeEach;\n"
			. "var afterEach = window.afterEach;\n"
			. "var beforeAll = window.beforeAll;\n"
			. "var afterAll = window.afterAll;\n"
			. "var jest = window.jest;\n";
	}

	private static function get_helpers_js() {
		$dom    = CLEFA_DEV_PATH . 'tests/js/helpers/dom.js';
		$real   = CLEFA_DEV_PATH . 'tests/js/helpers/real-dom.js';
		$filter = CLEFA_DEV_PATH . 'tests/js/helpers/filter-dom.js';

		$dom_js = is_file( $dom ) ? (string) file_get_contents( $dom ) : '';
		$dom_js = preg_replace( '/module\.exports\s*=\s*\{[^}]+\};\s*/', '', $dom_js );

		$real_js = is_file( $real ) ? (string) file_get_contents( $real ) : '';
		$real_js = preg_replace( '/window\.CLEFATest[\s\S]*$/', '', $real_js );

		$filter_js = is_file( $filter ) ? (string) file_get_contents( $filter ) : '';
		$filter_js = preg_replace( '/module\.exports\s*=\s*\{[^}]+\};\s*/', '', $filter_js );

		return $dom_js . "\n" . $real_js . "\n" . $filter_js . "\n"
			. "function clearTestDom(){var m=document.getElementById('clefa-test-mount');if(m){m.innerHTML='';}else{var b=document.body;if(b){b.innerHTML='<div id=\"clefa-test-mount\"></div>';}}}\n"
			. "function getTestMount(){var m=document.getElementById('clefa-test-mount');if(!m){m=document.createElement('div');m.id='clefa-test-mount';document.body.appendChild(m);}return m;}\n"
			. "window.CLEFATest.helpers.makeForm = makeForm;\n"
			. "window.CLEFATest.helpers.loadFixture = loadFixture;\n"
			. "window.CLEFATest.helpers.getFieldWrap = getFieldWrap;\n"
			. "window.CLEFATest.helpers.setFieldValue = setFieldValue;\n"
			. "window.CLEFATest.helpers.getErrorText = getErrorText;\n"
			. "window.CLEFATest.helpers.hasErrorClass = hasErrorClass;\n"
			. "window.CLEFATest.helpers.isFieldVisible = isFieldVisible;\n"
			. "window.CLEFATest.helpers.layoutHeight = layoutHeight;\n"
			. "window.CLEFATest.helpers.listenOnce = listenOnce;\n"
			. "window.CLEFATest.helpers.waitTransition = waitTransition;\n"
			. "window.CLEFATest.helpers.parseFormConfig = parseFormConfig;\n"
			. "window.CLEFATest.helpers.buildFilterWidget = buildFilterWidget;\n"
			. "window.loadFixture = loadFixture;\n"
			. "window.getFieldWrap = getFieldWrap;\n"
			. "window.setFieldValue = setFieldValue;\n"
			. "window.getErrorText = getErrorText;\n"
			. "window.hasErrorClass = hasErrorClass;\n"
			. "window.isFieldVisible = isFieldVisible;\n"
			. "window.layoutHeight = layoutHeight;\n"
			. "window.listenOnce = listenOnce;\n"
			. "window.waitTransition = waitTransition;\n";
	}
}
