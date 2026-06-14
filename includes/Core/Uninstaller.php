<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs when the plugin is deleted via the WordPress admin.
 * Registered via register_uninstall_hook() in the main plugin file.
 */
class CLEFA_Uninstaller {

	public static function uninstall() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$remove_data = (bool) get_option( 'clefa_uninstall_remove_data', false );

		if ( ! $remove_data ) {
			// Respect the user's choice - keep data unless explicitly opted in
			return;
		}

		self::drop_tables();
		self::delete_options();
		self::delete_upload_dirs();
		self::clear_scheduled_events();
	}

	private static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'clefa_audit_logs',
			$wpdb->prefix . 'clefa_test_logs',
			$wpdb->prefix . 'clefa_uploads',
			$wpdb->prefix . 'clefa_submissions',
			$wpdb->prefix . 'clefa_form_versions',
			$wpdb->prefix . 'clefa_forms',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		}

		delete_option( 'clefa_db_version' );
	}

	private static function delete_options() {
		global $wpdb;
		// Delete all plugin options (clefa_*)
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'clefa\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	}

	private static function delete_upload_dirs() {
		$upload_dir = wp_upload_dir();

		$dirs = array(
			trailingslashit( $upload_dir['basedir'] ) . 'clefa-temp',
			trailingslashit( $upload_dir['basedir'] ) . 'clefa-uploads',
		);

		foreach ( $dirs as $dir ) {
			if ( is_dir( $dir ) ) {
				self::rrmdir( $dir );
			}
		}
	}

	private static function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) { return; }
		$items = array_diff( (array) scandir( $dir ), array( '.', '..' ) );
		foreach ( $items as $item ) {
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			is_dir( $path ) ? self::rrmdir( $path ) : wp_delete_file( $path );
		}
		rmdir( $dir );
	}

	private static function clear_scheduled_events() {
		wp_clear_scheduled_hook( 'clefa_cleanup_temp_uploads' );
	}
}
