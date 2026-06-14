<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Dev_Page {

	const NONCE_ACTION = 'clefa_dev_action';

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'codelinden-elementor-form-addon' ) );
		}

		require_once CLEFA_PLUGIN_PATH . 'includes/Core/Testing.php';
		CLEFA_Testing::enable();

		if ( $this->is_post_action() ) {
			$this->process_post_action();
		}

		$page_slug = sanitize_key( wp_unslash( $_GET['page'] ?? 'clefa-dev' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$tab       = isset( $_GET['tab'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? sanitize_key( wp_unslash( $_GET['tab'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
			: ( 'clefa-tests' === $page_slug ? 'integration' : 'suites' );

		$flash           = $this->consume_flash();
		$can_run_shell   = $this->can_run_shell();
		$forms           = CLEFA_Tables::get_forms( array( 'per_page' => 200 ) );
		$form_id         = absint( wp_unslash( $_GET['form_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$form            = $form_id ? CLEFA_Tables::get_form( $form_id ) : null;
		$form_fields     = $form ? $this->extract_form_fields( $form ) : array();
		$test_history    = $form_id ? $this->get_test_history( $form_id ) : array();
		$coverage        = 'coverage' === $tab ? $this->get_coverage_data() : null;

		$template = CLEFA_TEMPLATE_PATH . 'admin/dev-hub.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	private function is_post_action() {
		return isset( $_POST['clefa_dev_action'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	private function can_run_shell() {
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || apply_filters( 'clefa_allow_dev_suite', false );
	}

	private function process_post_action() {
		check_admin_referer( self::NONCE_ACTION );

		$action = sanitize_key( wp_unslash( $_POST['clefa_dev_action'] ?? '' ) );
		$tab    = sanitize_key( wp_unslash( $_POST['clefa_tab'] ?? 'suites' ) );
		$page   = sanitize_key( wp_unslash( $_POST['clefa_page'] ?? 'clefa-dev' ) );

		$redirect_args = array(
			'page' => $page,
			'tab'  => $tab,
		);

		switch ( $action ) {
			case 'run_phpunit':
				if ( ! $this->can_run_shell() ) {
					break;
				}
				require_once CLEFA_PLUGIN_PATH . 'includes/Dev/Test_Suite_Runner.php';
				$result = CLEFA_Test_Suite_Runner::run_phpunit_report();
				$this->set_flash( array(
					'type'    => 'phpunit',
					'success' => $result['success'],
					'output'  => $result['output'],
					'parsed'  => $result['parsed'],
				) );
				$redirect_args['tab'] = 'suites';
				break;

			case 'seed_forms':
				require_once CLEFA_PLUGIN_PATH . 'includes/Dev/Test_Form_Seeder.php';
				$result = CLEFA_Test_Form_Seeder::seed_all();
				$this->set_flash( array(
					'type'    => 'seed',
					'success' => empty( $result['errors'] ),
					'data'    => $result,
				) );
				$redirect_args['tab'] = 'fixtures';
				break;

			case 'cleanup_all':
				require_once CLEFA_PLUGIN_PATH . 'includes/Dev/Test_Form_Seeder.php';
				$subs  = CLEFA_Testing::cleanup_all();
				$forms = CLEFA_Test_Form_Seeder::delete_seeded_forms();
				$this->set_flash( array(
					'type'    => 'cleanup_all',
					'success' => true,
					'data'    => array(
						'submissions_deleted' => $subs['submissions'],
						'test_logs_deleted'   => $subs['logs'],
						'forms_deleted'       => $forms,
					),
				) );
				$redirect_args['tab'] = 'fixtures';
				break;

			case 'run_integration':
				$form_id    = absint( wp_unslash( $_POST['form_id'] ?? 0 ) );
				$raw_cases  = wp_unslash( $_POST['test_cases'] ?? '[]' );
				$test_cases = json_decode( $raw_cases, true );

				if ( ! $form_id || ! is_array( $test_cases ) || empty( $test_cases ) ) {
					$this->set_flash( array(
						'type'    => 'integration',
						'success' => false,
						'message' => __( 'Add at least one test case first.', 'codelinden-elementor-form-addon' ),
					) );
				} else {
					require_once CLEFA_PLUGIN_PATH . 'includes/Tests/Form_Test_Runner.php';
					require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Condition_Engine.php';
					require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Validator.php';
					require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Sanitizer.php';
					require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

					$runner = new CLEFA_Form_Test_Runner();
					$result = $runner->run( $form_id, $test_cases );

					if ( is_wp_error( $result ) ) {
						$this->set_flash( array(
							'type'    => 'integration',
							'success' => false,
							'message' => $result->get_error_message(),
						) );
					} else {
						CLEFA_Audit_Log::write( 'test_run', array(
							'form_id'  => $form_id,
							'group_id' => $result['group_id'],
							'summary'  => $result['summary'],
						) );
						$this->set_flash( array(
							'type'    => 'integration',
							'success' => true,
							'data'    => $result,
						) );
					}
				}

				$redirect_args['tab']     = 'integration';
				$redirect_args['form_id'] = $form_id;
				break;

			case 'cleanup_group':
				$group_id = sanitize_text_field( wp_unslash( $_POST['group_id'] ?? '' ) );
				$form_id  = absint( wp_unslash( $_POST['form_id'] ?? 0 ) );

				if ( $group_id ) {
					CLEFA_Testing::cleanup_group( $group_id );
					$this->set_flash( array(
						'type'    => 'cleanup_group',
						'success' => true,
						'message' => __( 'Test data cleaned up.', 'codelinden-elementor-form-addon' ),
					) );
				}

				$redirect_args['tab']     = 'integration';
				$redirect_args['form_id'] = $form_id;
				break;

			default:
				break;
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private function flash_key() {
		return 'clefa_dev_flash_' . get_current_user_id();
	}

	private function set_flash( array $flash ) {
		if ( isset( $flash['data']['success'] ) ) {
			$flash['success'] = (bool) $flash['data']['success'];
		}
		set_transient( $this->flash_key(), $flash, 5 * MINUTE_IN_SECONDS );
	}

	private function consume_flash() {
		$flash = get_transient( $this->flash_key() );
		if ( false !== $flash ) {
			delete_transient( $this->flash_key() );
		}
		return is_array( $flash ) ? $flash : null;
	}

	private function extract_form_fields( array $form ) {
		$config = is_array( $form['config'] ?? null ) ? $form['config'] : array();
		$fields = array();

		foreach ( $config['steps'] ?? array() as $step ) {
			foreach ( $step['fields'] ?? array() as $field ) {
				if ( empty( $field['field_id'] ) ) {
					continue;
				}
				$fields[] = array(
					'field_id'   => $field['field_id'],
					'label'      => $field['label'] ?? $field['field_id'],
					'field_type' => $field['field_type'] ?? 'text',
					'required'   => ! empty( $field['required'] ),
				);
			}
		}

		return $fields;
	}

	private function get_test_history( $form_id ) {
		require_once CLEFA_PLUGIN_PATH . 'includes/Tests/Form_Test_Runner.php';
		$logs = CLEFA_Form_Test_Runner::get_logs( $form_id, 50, 1 );
		$groups = array();

		foreach ( $logs as $log ) {
			$gid = $log['test_group_id'] ?? '';
			if ( ! isset( $groups[ $gid ] ) ) {
				$groups[ $gid ] = array();
			}
			$groups[ $gid ][] = $log;
		}

		return $groups;
	}

	private function get_coverage_data() {
		$php = CLEFA_Testing::get_php_coverage_map();
		$js  = CLEFA_Testing::get_js_coverage_map();

		$php_tested = count( array_filter( $php, function( $r ) { return $r['has_test']; } ) );
		$js_tested  = count( array_filter( $js, function( $r ) { return $r['has_test']; } ) );

		return array(
			'php' => array(
				'items'   => $php,
				'total'   => count( $php ),
				'tested'  => $php_tested,
				'missing' => count( $php ) - $php_tested,
			),
			'js' => array(
				'items'   => $js,
				'total'   => count( $js ),
				'tested'  => $js_tested,
				'missing' => count( $js ) - $js_tested,
			),
			'testing_mode' => CLEFA_Testing::is_active(),
		);
	}
}
