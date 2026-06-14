<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin-level capability checks.
 *
 * All checks pass through apply_filters( 'clefa_user_can', $result, $cap, $user_id )
 * so site owners can override capability requirements without touching code.
 *
 * Default mapping: all CLEFA capabilities require 'manage_options'.
 * Override example:
 *   add_filter( 'clefa_user_can', function( $result, $cap, $user_id ) {
 *       if ( $cap === 'view_submissions' ) {
 *           return user_can( $user_id, 'edit_posts' );
 *       }
 *       return $result;
 *   }, 10, 3 );
 */
class CLEFA_Capabilities {

	/**
	 * Capability slugs and their default WordPress capability requirement.
	 *
	 * @var array<string, string>
	 */
	private static $map = array(
		'manage_forms'        => 'manage_options',
		'edit_form'           => 'manage_options',
		'delete_form'         => 'manage_options',
		'publish_form'        => 'manage_options',
		'view_submissions'    => 'manage_options',
		'delete_submission'   => 'manage_options',
		'export_submissions'  => 'manage_options',
		'run_tests'           => 'manage_options',
		'view_logs'           => 'manage_options',
		'manage_settings'     => 'manage_options',
	);

	/**
	 * Check whether the current (or given) user has a CLEFA capability.
	 *
	 * @param string   $clefa_cap  CLEFA capability slug (e.g. 'view_submissions').
	 * @param int|null $user_id    User to check; defaults to current user.
	 * @return bool
	 */
	public static function current_user_can( $clefa_cap, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$wp_cap = self::$map[ $clefa_cap ] ?? 'manage_options';
		$result = user_can( $user_id, $wp_cap );

		return (bool) apply_filters( 'clefa_user_can', $result, $clefa_cap, $user_id );
	}

	/**
	 * Die with a permissions error if the current user lacks a CLEFA capability.
	 *
	 * @param string $clefa_cap
	 */
	public static function require( $clefa_cap ) {
		if ( ! self::current_user_can( $clefa_cap ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'codelinden-elementor-form-addon' ), 403 );
		}
	}

	/**
	 * Return all registered capability slugs.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array_keys( self::$map );
	}
}
