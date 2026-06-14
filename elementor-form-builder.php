<?php
/**
 * Plugin Name:       CodeLinden Elementor Form Addon
 * Plugin URI:        https://codelinden.com
 * Description:       Advanced WordPress admin drag-and-drop form builder. Create multi-step forms, conditional logic, upload flows, data mapping and submit actions. Elementor widget selects a saved form and handles styling only.
 * Version:           1.0.7
 * Author:            CodeLinden
 * Author URI:        https://codelinden.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       codelinden-elementor-form-addon
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLEFA_PLUGIN_FILE', __FILE__ );
define( 'CLEFA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLEFA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLEFA_PLUGIN_VERSION', '1.0.6' );
define( 'CLEFA_DB_VERSION', '1.1.0' );
define( 'CLEFA_TEMPLATE_PATH', CLEFA_PLUGIN_PATH . 'templates/' );
define( 'CLEFA_ASSET_URL', CLEFA_PLUGIN_URL . 'assets/' );
define( 'CLEFA_TEXT_DOMAIN', 'codelinden-elementor-form-addon' );
define( 'CLEFA_DEV_PATH', CLEFA_PLUGIN_PATH . 'dev/' );

if ( ! defined( 'CLEFA_TESTING' ) ) {
	define( 'CLEFA_TESTING', false );
}

require_once CLEFA_PLUGIN_PATH . 'includes/Core/Installer.php';
require_once CLEFA_PLUGIN_PATH . 'includes/Core/Uninstaller.php';

register_activation_hook( CLEFA_PLUGIN_FILE, array( 'CLEFA_Installer', 'run_on_activation' ) );
register_deactivation_hook( CLEFA_PLUGIN_FILE, array( 'CLEFA_Installer', 'run_on_deactivation' ) );
register_uninstall_hook( CLEFA_PLUGIN_FILE, array( 'CLEFA_Uninstaller', 'uninstall' ) );

require_once CLEFA_PLUGIN_PATH . 'includes/Core/Plugin.php';

add_action( 'plugins_loaded', array( 'CLEFA_Plugin', 'get_instance' ) );
