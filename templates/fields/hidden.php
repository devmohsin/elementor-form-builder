<?php
/**
 * Field template: hidden
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id = $field['field_id'] ?? '';
$adv      = $field['advanced'] ?? array();

$val = (string) $value;

// Dynamic token substitution for hidden fields
if ( ! empty( $adv['dynamic_source'] ) ) {
	$source = $adv['dynamic_source'];
	switch ( $source ) {
		case 'user_id':
			$val = (string) get_current_user_id();
			break;
		case 'user_email':
			$user = wp_get_current_user();
			$val  = $user->ID ? $user->user_email : '';
			break;
		case 'user_role':
			if ( is_user_logged_in() ) {
				$roles = wp_get_current_user()->roles;
				$val   = ! empty( $roles ) ? (string) $roles[0] : '';
			}
			break;
		case 'post_id':
			$val = (string) get_queried_object_id();
			break;
		case 'current_url':
			$val = esc_url_raw( ( is_ssl() ? 'https' : 'http' ) . '://' . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' ) );
			break;
		case 'referrer':
			$val = esc_url_raw( wp_get_referer() ?: '' );
			break;
		case 'date':
			$val = current_time( 'Y-m-d' );
			break;
		case 'timestamp':
			$val = (string) time();
			break;
		case 'random_token':
			$val = wp_generate_password( 20, false );
			break;
		case 'utm_source':
		case 'utm_medium':
		case 'utm_campaign':
		case 'utm_term':
		case 'utm_content':
			$val = sanitize_text_field( $_GET[ $source ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			break;
		default:
			// Handle query_param:{key} and user_meta:{key} and post_meta:{key}
			if ( 0 === strpos( $source, 'query_param:' ) ) {
				$param = sanitize_key( substr( $source, strlen( 'query_param:' ) ) );
				$val   = sanitize_text_field( $_GET[ $param ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( 0 === strpos( $source, 'user_meta:' ) ) {
				if ( is_user_logged_in() ) {
					$meta_key = sanitize_key( substr( $source, strlen( 'user_meta:' ) ) );
					$val      = (string) get_user_meta( get_current_user_id(), $meta_key, true );
				}
			} elseif ( 0 === strpos( $source, 'post_meta:' ) ) {
				$meta_key = sanitize_key( substr( $source, strlen( 'post_meta:' ) ) );
				$post_id  = get_queried_object_id();
				$val      = $post_id ? (string) get_post_meta( $post_id, $meta_key, true ) : '';
			} else {
				// Legacy: bare query param key
				$val = sanitize_text_field( $_GET[ $source ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			break;
	}
}

if ( ! empty( $adv['signed'] ) ) {
	$val = CLEFA_Form_Sanitizer::sign_hidden_value( $val );
}
?>
<input
	type="hidden"
	id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
	name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
	data-clefa-input
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	value="<?php echo esc_attr( $val ); ?>"
/>
