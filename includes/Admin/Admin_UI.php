<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared admin screen helpers.
 */
class CLEFA_Admin_UI {

	private static $notice_clear_printed = false;

	/**
	 * Register hooks — call once from Loader.
	 */
	public static function init() {
		// After every admin notice (core + third-party), clear floats before our page HTML.
		add_action( 'admin_notices', array( __CLASS__, 'clear_after_notices' ), 999 );
		add_action( 'all_admin_notices', array( __CLASS__, 'clear_after_notices' ), 999 );
	}

	/**
	 * True when viewing any Form Addon admin screen.
	 */
	public static function is_clefa_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		return 0 === strpos( $page, 'clefa-' );
	}

	/**
	 * Output clearfix after the admin-notices stack (runs via admin_notices hook).
	 */
	public static function clear_after_notices() {
		if ( self::$notice_clear_printed || ! is_admin() || ! self::is_clefa_page() ) {
			return;
		}
		self::$notice_clear_printed = true;
		echo '<div class="clear clefa-admin-notice-clear"></div>';
	}

	/**
	 * Optional settings API messages at the top of a page wrap.
	 *
	 * @param string $settings_group settings_errors() group slug.
	 */
	public static function settings_messages( $settings_group = '' ) {
		if ( $settings_group ) {
			settings_errors( $settings_group );
		}
	}
}
