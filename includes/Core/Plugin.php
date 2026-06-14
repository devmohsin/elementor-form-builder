<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Plugin {

	private static $instance = null;

	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function load_dependencies() {
		require_once CLEFA_PLUGIN_PATH . 'includes/Database/Tables.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Core/Loader.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Core/Plugin_Dependencies.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Core/Assets.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Core/Audit_Log.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Core/Capabilities.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Builder/Config_Normalizer.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Builder/Form_Templates.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/REST/Routes.php';

		require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Condition_Engine.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Sanitizer.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Validation/Validation_Registry.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Validator.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Escaper.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Routing_Engine.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Action_Runner.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Submission_Handler.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_Renderer.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Forms/Form_State_Manager.php';

		require_once CLEFA_PLUGIN_PATH . 'includes/Filter/Filter_State.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/Filter/Filter_Query_Builder.php';

		require_once CLEFA_PLUGIN_PATH . 'includes/Notifications/Notification_Manager.php';

		// Settings page is needed on frontend too (for get() helper)
		require_once CLEFA_PLUGIN_PATH . 'includes/Admin/Settings_Page.php';

		if ( is_admin() ) {
			require_once CLEFA_PLUGIN_PATH . 'includes/Admin/Admin_UI.php';
			require_once CLEFA_PLUGIN_PATH . 'includes/Admin/Menu.php';
			require_once CLEFA_PLUGIN_PATH . 'includes/Admin/Forms_Page.php';
			require_once CLEFA_PLUGIN_PATH . 'includes/Admin/Edit_Form_Page.php';
			require_once CLEFA_PLUGIN_PATH . 'includes/Admin/Submissions_Page.php';
			require_once CLEFA_PLUGIN_PATH . 'includes/Admin/Tests_Page.php';
			require_once CLEFA_PLUGIN_PATH . 'includes/Admin/Dev_Page.php';
			require_once CLEFA_PLUGIN_PATH . 'includes/Dev/Js_Test_Loader.php';
			CLEFA_Js_Test_Loader::init();
			require_once CLEFA_PLUGIN_PATH . 'includes/Admin/Logs_Page.php';
		}
	}

	private function init_hooks() {
		CLEFA_Installer::maybe_run();
		CLEFA_Loader::init();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( 'CLEFA_Validation_Registry', 'init' ), 11 );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );

		// Notification system hooks into clefa_after_submission_save
		CLEFA_Notification_Manager::init();

		// Cron: temp upload cleanup
		add_action( 'clefa_cleanup_temp_uploads', array( $this, 'run_upload_cleanup' ) );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( 'CLEFA_Plugin_Dependencies', 'render_admin_notices' ) );
		}
	}

	public function run_upload_cleanup() {
		require_once CLEFA_PLUGIN_PATH . 'includes/Upload/Upload_Handler.php';
		CLEFA_Upload_Handler::cleanup_expired_temp_files();
	}

	public function register_shortcodes() {
		add_shortcode( 'clefa_form', array( 'CLEFA_Form_Renderer', 'shortcode' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			CLEFA_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( CLEFA_PLUGIN_FILE ) ) . '/languages'
		);
	}

	public function register_elementor_widgets( $widgets_manager ) {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}
		require_once CLEFA_PLUGIN_PATH . 'includes/Elementor/Form_Widget.php';
		$widgets_manager->register( new CLEFA_Form_Widget() );

		require_once CLEFA_PLUGIN_PATH . 'includes/Elementor/Filter_Widget.php';
		$widgets_manager->register( new CLEFA_Filter_Widget() );
	}
}
