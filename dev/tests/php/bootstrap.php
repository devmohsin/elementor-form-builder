<?php
/**
 * PHPUnit bootstrap — minimal WordPress-like environment for unit tests.
 *
 * Run from plugin root:
 *   vendor/bin/phpunit
 *   vendor/bin/phpunit -c dev/phpunit.xml
 */

define( 'ABSPATH', __DIR__ . '/../../../../../../../' );
define( 'CLEFA_PLUGIN_PATH', dirname( __DIR__, 3 ) . DIRECTORY_SEPARATOR );
define( 'CLEFA_PLUGIN_URL', 'http://localhost/wp-content/plugins/elementor-form-builder/' );
define( 'CLEFA_VERSION', '1.0.0-test' );
define( 'CLEFA_DEV_PATH', CLEFA_PLUGIN_PATH . 'dev/' );
define( 'CLEFA_TESTING', true );

require_once __DIR__ . '/WordPressActionStubs.php';

// -----------------------------------------------------------------------
// Minimal WordPress function stubs
// -----------------------------------------------------------------------

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { return $text; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $t ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = '' ) {
		return esc_html( $text );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $t ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ) ?: ''; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $t ) { return trim( strip_tags( $t ) ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $t ) { return trim( strip_tags( $t ) ); }
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $e ) {
		$e = trim( (string) $e );
		return filter_var( $e, FILTER_SANITIZE_EMAIL ) ?: '';
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ) ?? ''; }
}
if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $c ) { return preg_replace( '/[^a-zA-Z0-9_\-]/', '', $c ) ?? ''; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $t ) { return strip_tags( $t, '<p><a><strong><em><ul><ol><li><br><span><div>' ); }
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		global $clefa_test_filters;
		if ( empty( $clefa_test_filters[ $hook ] ) ) {
			return $value;
		}
		foreach ( $clefa_test_filters[ $hook ] as $callback ) {
			$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
		}
		return $value;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		global $clefa_test_filters;
		if ( ! isset( $clefa_test_filters ) ) {
			$clefa_test_filters = array();
		}
		if ( ! isset( $clefa_test_filters[ $hook ] ) ) {
			$clefa_test_filters[ $hook ] = array();
		}
		$clefa_test_filters[ $hook ][] = $callback;
	}
}
if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( $hook, $callback, $priority = 10 ) {}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		global $clefa_test_actions;
		if ( empty( $clefa_test_actions[ $hook ] ) ) {
			return;
		}
		foreach ( $clefa_test_actions[ $hook ] as $callback ) {
			call_user_func_array( $callback, $args );
		}
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		global $clefa_test_actions;
		if ( ! isset( $clefa_test_actions ) ) {
			$clefa_test_actions = array();
		}
		if ( ! isset( $clefa_test_actions[ $hook ] ) ) {
			$clefa_test_actions[ $hook ] = array();
		}
		$clefa_test_actions[ $hook ][] = $callback;
	}
}
if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) { return false; }
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		global $clefa_test_current_user_id;
		return isset( $clefa_test_current_user_id ) ? (int) $clefa_test_current_user_id : 0;
	}
}
if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		global $clefa_test_current_user_id;
		return isset( $clefa_test_current_user_id ) && (int) $clefa_test_current_user_id > 0;
	}
}
if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		return (object) array( 'ID' => 0, 'roles' => array(), 'user_email' => '' );
	}
}
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key, $single = false ) {
		global $clefa_test_user_meta;
		$val = $clefa_test_user_meta[ $user_id ][ $key ] ?? null;
		if ( $single ) {
			return is_array( $val ) ? $val : ( $val ?? '' );
		}
		return isset( $val ) ? array( $val ) : array();
	}
}
if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value ) {
		global $clefa_test_user_meta;
		if ( ! isset( $clefa_test_user_meta ) ) {
			$clefa_test_user_meta = array();
		}
		$clefa_test_user_meta[ $user_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_user_meta' ) ) {
	function delete_user_meta( $user_id, $key ) {
		global $clefa_test_user_meta;
		unset( $clefa_test_user_meta[ $user_id ][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'user_can' ) ) {
	function user_can( $user_id, $cap ) {
		global $clefa_test_user_caps;
		if ( isset( $clefa_test_user_caps[ $user_id ][ $cap ] ) ) {
			return (bool) $clefa_test_user_caps[ $user_id ][ $cap ];
		}
		return false;
	}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new \RuntimeException( strip_tags( (string) $message ) );
	}
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $data ) {
		return $data;
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'test-salt-' . $scheme;
	}
}
if ( ! function_exists( 'hash_equals' ) ) {
	function hash_equals( $known, $user ) {
		return hash( 'sha256', $known ) === hash( 'sha256', $user );
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		global $clefa_test_transients;
		return $clefa_test_transients[ $key ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiry = 0 ) {
		global $clefa_test_transients;
		if ( ! isset( $clefa_test_transients ) ) {
			$clefa_test_transients = array();
		}
		$clefa_test_transients[ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		global $clefa_test_transients;
		unset( $clefa_test_transients[ $key ] );
		return true;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $key, $value ) {
		global $clefa_test_options;
		if ( ! isset( $clefa_test_options ) ) {
			$clefa_test_options = array();
		}
		if ( ! isset( $clefa_test_options[ $key ] ) ) {
			$clefa_test_options[ $key ] = $value;
		}
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		global $clefa_test_options;
		if ( ! isset( $clefa_test_options ) ) {
			$clefa_test_options = array();
		}
		$clefa_test_options[ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook ) { return false; }
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook ) { return true; }
}
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook ) {}
}
if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules() {}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args ) {}
}
if ( ! function_exists( 'sprintf' ) ) {
	// already available in PHP — just ensure it's not accidentally missing
}
if ( ! defined( 'CLEFA_DB_VERSION' ) ) {
	define( 'CLEFA_DB_VERSION', '1.0.0' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() { return 0; }
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true ) {
		return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' ), 0, $length );
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		if ( 'c' === $type ) {
			return date( 'c' );
		}
		return 'mysql' === $type ? date( 'Y-m-d H:i:s' ) : ( 'Y-m-d' === $type ? date( 'Y-m-d' ) : date( 'H:i:s' ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) { return abs( (int) $v ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) { return json_encode( $data ); }
}
if ( ! function_exists( 'wp_hash' ) ) {
	function wp_hash( $data ) { return hash_hmac( 'sha256', $data, 'test-secret' ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		$url = (string) $url;
		if ( preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}
		$sanitized = filter_var( $url, FILTER_SANITIZE_URL );
		return $sanitized ?: '';
	}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $t ) {
		return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $t ) {
		return addslashes( $t );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'name' === $show ? 'Test Site' : '';
	}
}
if ( ! function_exists( 'get_site_url' ) ) {
	function get_site_url() {
		return 'http://localhost';
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $clefa_test_options;
		if ( isset( $clefa_test_options ) && array_key_exists( $option, $clefa_test_options ) ) {
			return $clefa_test_options[ $option ];
		}
		$map = array(
			'admin_email'    => 'admin@example.com',
			'posts_per_page' => 10,
		);
		return array_key_exists( $option, $map ) ? $map[ $option ] : $default;
	}
}
if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '' ) {
		global $clefa_test_mails;
		if ( ! isset( $clefa_test_mails ) ) {
			$clefa_test_mails = array();
		}
		$clefa_test_mails[] = array(
			'to'      => $to,
			'subject' => $subject,
			'message' => $message,
			'headers' => $headers,
		);
		return true;
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return true;
	}
}
if ( ! function_exists( 'get_users' ) ) {
	function get_users( $args = array() ) {
		return array();
	}
}
if ( ! function_exists( 'add_settings_error' ) ) {
	function add_settings_error( $setting, $code, $message, $type = 'error' ) {
		global $clefa_test_settings_errors;
		if ( ! isset( $clefa_test_settings_errors ) ) {
			$clefa_test_settings_errors = array();
		}
		$clefa_test_settings_errors[] = compact( 'setting', 'code', 'message', 'type' );
	}
}
if ( ! function_exists( 'settings_errors' ) ) {
	function settings_errors( $setting = '' ) {
		global $clefa_test_settings_errors;
		if ( empty( $clefa_test_settings_errors ) ) {
			return;
		}
		foreach ( $clefa_test_settings_errors as $err ) {
			if ( $setting && $err['setting'] !== $setting ) {
				continue;
			}
			printf(
				'<div class="%1$s"><p>%2$s</p></div>',
				esc_attr( 'updated' === $err['type'] ? 'notice notice-success' : 'notice notice-error' ),
				esc_html( $err['message'] )
			);
		}
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://localhost/wp-admin/' . ltrim( (string) $path, '/' );
	}
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		protected $params      = array();
		protected $json_params = null;
		protected $body        = '';

		public function __construct( $method = '', $route = '', array $params = array() ) {
			if ( is_array( $method ) && '' === $route && empty( $params ) ) {
				$this->params = $method;
				return;
			}
			unset( $method, $route );
			$this->params = $params;
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function set_body( $body ) {
			$this->body        = (string) $body;
			$this->json_params = json_decode( $this->body, true );
		}

		public function get_json_params() {
			return is_array( $this->json_params ) ? $this->json_params : array();
		}
	}
}

if ( ! function_exists( 'clefa_test_reset_forms_store' ) ) {
	function clefa_test_reset_forms_store() {
		global $clefa_test_forms, $clefa_test_form_versions;
		$clefa_test_forms          = array();
		$clefa_test_form_versions   = array();
		if ( isset( $GLOBALS['wpdb'] ) ) {
			$GLOBALS['wpdb']->insert_id = 0;
		}
	}
}

require_once CLEFA_PLUGIN_PATH . 'includes/Core/Testing.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Core/Plugin_Dependencies.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Admin/Admin_UI.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Builder/Config_Normalizer.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Validation/Validation_Registry.php';
CLEFA_Validation_Registry::init();
require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Validator.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Condition_Engine.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Sanitizer.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Routing_Engine.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Escaper.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Filter/Filter_State.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Filter/Filter_Query_Builder.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Redirect_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Create_Post_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Register_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Login_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Update_Post_Meta_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Update_User_Meta_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/ACF_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/ACF_Repeater_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Taxonomy_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Role_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Lost_Password_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/WC_Product_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Webhook_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Send_Email_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Save_Submission_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Notifications/Notification_Manager.php';

// -----------------------------------------------------------------------
// Additional classes for expanded test coverage
// -----------------------------------------------------------------------

// Minimal mock for CLEFA_Tables — controlled by tests via static property.
if ( ! class_exists( 'CLEFA_Tables' ) ) {
	class CLEFA_Tables {
		public static $mock_form = null;

		public static function get_form( $form_id ) {
			if ( null !== self::$mock_form ) {
				return self::$mock_form;
			}

			global $clefa_test_forms;
			$form_id = absint( $form_id );
			if ( empty( $clefa_test_forms[ $form_id ] ) ) {
				return null;
			}

			$row = $clefa_test_forms[ $form_id ];
			if ( ! empty( $row['config_json'] ) ) {
				$row['config'] = json_decode( $row['config_json'], true );
			}
			if ( ! empty( $row['feature_map_json'] ) ) {
				$row['feature_map'] = json_decode( $row['feature_map_json'], true );
			}
			return $row;
		}

		public static function invalidate_form_cache( $form_id ) {
			unset( $form_id );
		}

		public static function get_forms( $args = array() ) {
			global $clefa_test_forms;
			return array_values( $clefa_test_forms ?? array() );
		}

		public static function create() {}
	}
}

// Minimal mock for CLEFA_Settings_Page — returns defaults.
if ( ! class_exists( 'CLEFA_Settings_Page' ) ) {
	class CLEFA_Settings_Page {
		public static $overrides = array();
		public static function get( $key, $default = null ) {
			return self::$overrides[ $key ] ?? $default;
		}
	}
}

// Minimal mock for CLEFA_Audit_Log — captures last write and full event log for assertions.
if ( ! class_exists( 'CLEFA_Audit_Log' ) ) {
	class CLEFA_Audit_Log {
		public static $last_event   = null;
		public static $last_context = null;
		public static function write( $event, array $context = array() ) {
			global $clefa_test_audit_events;
			self::$last_event   = $event;
			self::$last_context  = $context;
			if ( ! isset( $clefa_test_audit_events ) ) {
				$clefa_test_audit_events = array();
			}
			$clefa_test_audit_events[] = array(
				'event'   => $event,
				'context' => $context,
			);
		}
	}
}

// Minimal mock for CLEFA_Form_Renderer — returns a stub notice string.
if ( ! class_exists( 'CLEFA_Form_Renderer' ) ) {
	class CLEFA_Form_Renderer {
		public static function render_notice( $message, $type, $form_id = 0 ) {
			return '<div class="clefa-notice clefa-notice--' . $type . '">' . $message . '</div>';
		}
	}
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Confirm_Password_Action.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Change_Password_Action.php';
require_once __DIR__ . '/FixtureFormTestHelper.php';
require_once __DIR__ . '/suite/ProgrammaticFormBuilder.php';
require_once __DIR__ . '/suite/ProgrammaticFormTestRunner.php';
require_once __DIR__ . '/suite/SubmissionFlowTestRunner.php';
require_once __DIR__ . '/suite/FormScenarioCatalog.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Action_Runner.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Core/Capabilities.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_State_Manager.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Core/Installer.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Dev/Jest_Result_Parser.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Dev/PhpUnit_Result_Parser.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Submission_Handler.php';

CLEFA_Notification_Manager::init();

if ( ! isset( $GLOBALS['wpdb'] ) ) {
	class CLEFA_Test_wpdb {
		public $prefix = 'wp_';
		public $insert_id = 0;

		public function prepare( $query, ...$args ) {
			if ( empty( $args ) ) {
				return $query;
			}
			$index = 0;
			return preg_replace_callback(
				'/%[dfs]/',
				static function () use ( $args, &$index ) {
					$value = $args[ $index++ ] ?? '';
					if ( is_numeric( $value ) ) {
						return (string) (int) $value;
					}
					return "'" . str_replace( "'", "''", (string) $value ) . "'";
				},
				$query
			);
		}

		public function get_row( $query, $output = OBJECT, $y = 0 ) {
			unset( $y );
			global $clefa_test_forms;

			if ( preg_match( '/FROM\s+\S+clefa_forms\s+WHERE\s+id\s*=\s*(\d+)/i', $query, $matches ) ) {
				$id  = (int) $matches[1];
				$row = $clefa_test_forms[ $id ] ?? null;
				if ( ! $row ) {
					return null;
				}
				if ( ARRAY_A === $output ) {
					return $row;
				}
				return (object) $row;
			}

			return null;
		}

		public function insert( $table, $data, $format = null ) {
			unset( $format );
			global $clefa_test_submissions, $clefa_test_forms, $clefa_test_form_versions;

			if ( false !== strpos( (string) $table, 'clefa_forms' ) ) {
				if ( ! isset( $clefa_test_forms ) ) {
					$clefa_test_forms = array();
				}
				$id = empty( $clefa_test_forms ) ? 1 : ( max( array_keys( $clefa_test_forms ) ) + 1 );
				$data['id'] = $id;
				$clefa_test_forms[ $id ] = $data;
				$this->insert_id           = $id;
				return 1;
			}

			if ( false !== strpos( (string) $table, 'clefa_form_versions' ) ) {
				if ( ! isset( $clefa_test_form_versions ) ) {
					$clefa_test_form_versions = array();
				}
				$id = count( $clefa_test_form_versions ) + 1;
				$data['id'] = $id;
				$clefa_test_form_versions[ $id ] = $data;
				$this->insert_id                 = $id;
				return 1;
			}

			if ( false === strpos( (string) $table, 'clefa_submissions' ) ) {
				return false;
			}
			if ( ! isset( $clefa_test_submissions ) ) {
				$clefa_test_submissions = array();
			}
			$id = count( $clefa_test_submissions ) + 1;
			$data['id'] = $id;
			$clefa_test_submissions[ $id ] = $data;
			$this->insert_id = $id;
			return 1;
		}

		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			unset( $format, $where_format );
			global $clefa_test_submissions, $clefa_test_forms;
			$id = (int) ( $where['id'] ?? 0 );

			if ( false !== strpos( (string) $table, 'clefa_forms' ) ) {
				if ( ! $id || empty( $clefa_test_forms[ $id ] ) ) {
					return false;
				}
				$clefa_test_forms[ $id ] = array_merge( $clefa_test_forms[ $id ], $data );
				return 1;
			}

			if ( ! $id || empty( $clefa_test_submissions[ $id ] ) ) {
				return false;
			}
			$clefa_test_submissions[ $id ] = array_merge( $clefa_test_submissions[ $id ], $data );
			return 1;
		}

		public function delete( $table, $where, $where_format = null ) {
			unset( $where_format );
			global $clefa_test_forms;
			$id = (int) ( $where['id'] ?? 0 );
			if ( false !== strpos( (string) $table, 'clefa_forms' ) && ! empty( $clefa_test_forms[ $id ] ) ) {
				unset( $clefa_test_forms[ $id ] );
				return 1;
			}
			return false;
		}
	}
	$GLOBALS['wpdb'] = new CLEFA_Test_wpdb();
}
