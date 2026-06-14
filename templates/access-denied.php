<?php
/**
 * Access-denied template for forms that require login.
 *
 * Variables from Form_Renderer:
 * @var array  $form      Form row from DB.
 * @var array  $config    Decoded form config.
 * @var int    $form_id   Form ID.
 * @var array  $settings  Form settings array.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$login_message = $settings['login_message']
	?? __( 'You must be logged in to submit this form.', 'codelinden-elementor-form-addon' );

$login_action  = $settings['login_action'] ?? 'message'; // message | form | redirect
$redirect_url  = esc_url( $settings['login_redirect_url'] ?? wp_login_url( get_permalink() ) );
$allowed_roles = array_filter( (array) ( $settings['allowed_roles'] ?? array() ) );

// Role check (if applicable)
if ( is_user_logged_in() && ! empty( $allowed_roles ) ) {
	$user          = wp_get_current_user();
	$has_role      = (bool) array_intersect( $user->roles, $allowed_roles );
	if ( ! $has_role ) {
		$login_message = $settings['role_denied_message']
			?? __( 'You do not have the required role to access this form.', 'codelinden-elementor-form-addon' );
		$login_action  = 'message'; // Always show message for role deny
	} else {
		return; // Role OK — render nothing (caller renders the actual form)
	}
}
?>
<div
	class="clefa-access-denied"
	data-clefa-access-denied
	data-clefa-form-id="<?php echo esc_attr( $form_id ); ?>"
>
	<?php if ( 'redirect' === $login_action ) : ?>
	<script>window.location.href = '<?php echo esc_js( $redirect_url ); ?>';</script>
	<p class="clefa-access-message"><?php echo esc_html( $login_message ); ?></p>
	<a class="clefa-btn clefa-btn-primary" href="<?php echo $redirect_url; ?>"><?php esc_html_e( 'Log In', 'codelinden-elementor-form-addon' ); ?></a>

	<?php elseif ( 'form' === $login_action ) : ?>
	<p class="clefa-access-message"><?php echo esc_html( $login_message ); ?></p>
	<?php
		$return_url = add_query_arg( 'redirect_to', urlencode( get_permalink() ?: '' ), wp_login_url() );
		wp_login_form( array(
			'redirect'       => esc_url( $return_url ),
			'id_username'    => 'clefa-login-user-' . esc_attr( $form_id ),
			'id_password'    => 'clefa-login-pw-' . esc_attr( $form_id ),
			'label_username' => __( 'Username or Email', 'codelinden-elementor-form-addon' ),
			'label_password' => __( 'Password', 'codelinden-elementor-form-addon' ),
			'label_log_in'   => __( 'Log In', 'codelinden-elementor-form-addon' ),
		) );
	?>

	<?php else : // 'message' (default) ?>
	<p class="clefa-access-message"><?php echo esc_html( $login_message ); ?></p>
	<?php if ( ! is_user_logged_in() ) : ?>
	<a class="clefa-btn clefa-btn-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
		<?php esc_html_e( 'Log In', 'codelinden-elementor-form-addon' ); ?>
	</a>
	<?php endif; ?>
	<?php endif; ?>
</div>
