<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects optional/required third-party plugin availability and exposes
 * dependency metadata for the admin builder UI.
 */
class CLEFA_Plugin_Dependencies {

	const DEP_ELEMENTOR     = 'elementor';
	const DEP_ELEMENTOR_PRO = 'elementor_pro';
	const DEP_ACF           = 'acf';
	const DEP_ACF_PRO       = 'acf_pro';
	const DEP_WOOCOMMERCE   = 'woocommerce';

	/** @var array|null Cached status map. */
	private static $status = null;

	/**
	 * Return availability map for all tracked dependencies.
	 *
	 * @return array<string,bool>
	 */
	public static function get_status() {
		if ( null !== self::$status ) {
			return self::$status;
		}

		self::$status = array(
			self::DEP_ELEMENTOR     => self::has_elementor(),
			self::DEP_ELEMENTOR_PRO => self::has_elementor_pro(),
			self::DEP_ACF           => self::has_acf(),
			self::DEP_ACF_PRO       => self::has_acf_pro(),
			self::DEP_WOOCOMMERCE   => self::has_woocommerce(),
		);

		return self::$status;
	}

	public static function is_available( $dependency ) {
		$status = self::get_status();
		return ! empty( $status[ $dependency ] );
	}

	/**
	 * Check whether every requirement in the list is satisfied.
	 *
	 * @param array|string $requires Single key or list of keys.
	 */
	public static function meets_requirements( $requires ) {
		if ( empty( $requires ) ) {
			return true;
		}
		if ( ! is_array( $requires ) ) {
			$requires = array( $requires );
		}
		foreach ( $requires as $dep ) {
			if ( ! self::is_available( $dep ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Human-readable message for the first missing dependency.
	 *
	 * @param array|string $requires
	 */
	public static function get_missing_message( $requires ) {
		if ( ! is_array( $requires ) ) {
			$requires = array( $requires );
		}
		foreach ( $requires as $dep ) {
			if ( ! self::is_available( $dep ) ) {
				return self::get_label( $dep );
			}
		}
		return '';
	}

	/**
	 * Short label shown on disabled sidebar / picker items.
	 */
	public static function get_label( $dependency ) {
		switch ( $dependency ) {
			case self::DEP_ELEMENTOR:
				return __( 'Requires Elementor', 'codelinden-elementor-form-addon' );
			case self::DEP_ELEMENTOR_PRO:
				return __( 'Requires Elementor Pro', 'codelinden-elementor-form-addon' );
			case self::DEP_ACF:
				return __( 'Requires Advanced Custom Fields', 'codelinden-elementor-form-addon' );
			case self::DEP_ACF_PRO:
				return __( 'Requires ACF Pro (repeater fields)', 'codelinden-elementor-form-addon' );
			case self::DEP_WOOCOMMERCE:
				return __( 'Requires WooCommerce', 'codelinden-elementor-form-addon' );
			default:
				return __( 'Required plugin not active', 'codelinden-elementor-form-addon' );
		}
	}

	public static function has_elementor() {
		return defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' );
	}

	public static function has_elementor_pro() {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}

	public static function has_acf() {
		return function_exists( 'acf' ) || class_exists( 'ACF', false );
	}

	public static function has_acf_pro() {
		if ( ! self::has_acf() ) {
			return false;
		}
		if ( defined( 'ACF_PRO' ) && ACF_PRO ) {
			return true;
		}
		if ( function_exists( 'acf_is_pro' ) && acf_is_pro() ) {
			return true;
		}
		// Repeater is a Pro-only field type — reliable fallback detection.
		if ( function_exists( 'acf_get_field_type' ) ) {
			return (bool) acf_get_field_type( 'repeater' );
		}
		return false;
	}

	public static function has_woocommerce() {
		return class_exists( 'WooCommerce', false );
	}

	/**
	 * Collect dependency messages for admin screens.
	 *
	 * @return array<int,array{level:string,message:string}>
	 */
	public static function get_notice_items() {
		$items = array();

		if ( ! self::has_elementor() ) {
			$items[] = array(
				'level'   => 'warning',
				'message' => __( 'Elementor is not active. Elementor widgets will not be available until Elementor is installed and activated.', 'codelinden-elementor-form-addon' ),
			);
		} elseif ( ! self::has_elementor_pro() ) {
			$items[] = array(
				'level'   => 'warning',
				'message' => __( 'Elementor Pro is not active. Pro-only features (e.g. advanced loop integration) are disabled in the builder.', 'codelinden-elementor-form-addon' ),
			);
		}

		if ( ! self::has_acf() ) {
			$items[] = array(
				'level'   => 'info',
				'message' => __( 'ACF is not active — ACF field actions and ACF mapping targets are disabled.', 'codelinden-elementor-form-addon' ),
			);
		} elseif ( ! self::has_acf_pro() ) {
			$items[] = array(
				'level'   => 'info',
				'message' => __( 'ACF Pro is not active — ACF Repeater actions are disabled.', 'codelinden-elementor-form-addon' ),
			);
		}

		if ( ! self::has_woocommerce() ) {
			$items[] = array(
				'level'   => 'info',
				'message' => __( 'WooCommerce is not active — product actions and WooCommerce mapping are disabled.', 'codelinden-elementor-form-addon' ),
			);
		}

		return $items;
	}

	/**
	 * Standard WordPress-style notices rendered inside page templates.
	 */
	public static function render_page_notices() {
		$items = self::get_notice_items();
		if ( empty( $items ) ) {
			return;
		}

		echo '<div class="clefa-page-notices" data-clefa-role="page-notices">';
		foreach ( $items as $item ) {
			$level = 'warning' === $item['level'] ? 'warning' : 'info';
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p><strong>%2$s</strong> %3$s</p></div>',
				esc_attr( $level ),
				esc_html__( 'Form Addon:', 'codelinden-elementor-form-addon' ),
				esc_html( $item['message'] )
			);
		}
		echo '</div>';
	}

	/**
	 * Admin notice hook — kept for screens that do not render inline notices.
	 */
	public static function render_admin_notices() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 0 !== strpos( $page, 'clefa-' ) ) {
			return;
		}

		self::render_page_notices();
	}

	/**
	 * Compact inline notices rendered inside the form builder.
	 */
	public static function render_builder_notices() {
		$items = self::get_notice_items();
		if ( empty( $items ) ) {
			return;
		}

		echo '<div class="clefa-dep-notices" data-clefa-role="dependency-notices">';
		foreach ( $items as $item ) {
			printf(
				'<div class="clefa-dep-notice clefa-dep-notice--%s" data-clefa-dep-notice="%s"><span class="dashicons dashicons-info-outline"></span> %s</div>',
				esc_attr( $item['level'] ),
				esc_attr( $item['level'] ),
				esc_html( $item['message'] )
			);
		}
		echo '</div>';
	}

	/**
	 * Attach availability + disabled_reason to a field or action definition.
	 */
	public static function enrich_definition( array $def ) {
		$requires = $def['requires'] ?? array();
		if ( ! is_array( $requires ) ) {
			$requires = array( $requires );
		}
		$def['requires']   = $requires;
		$def['available']  = self::meets_requirements( $requires );
		if ( ! $def['available'] && empty( $def['disabled_reason'] ) ) {
			$def['disabled_reason'] = self::get_missing_message( $requires );
		}
		return $def;
	}
}
