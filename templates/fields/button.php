<?php
/**
 * Field template: standalone button (non-submit action button)
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$label   = $field['label']        ?? __( 'Button', 'codelinden-elementor-form-addon' );
$style   = $field['button_style'] ?? 'primary';   // primary | secondary | ghost
$url     = $field['button_url']   ?? '';
$target  = ! empty( $field['button_new_tab'] ) ? '_blank' : '_self';
$cls     = 'clefa-btn clefa-btn-' . sanitize_html_class( $style ) . ' clefa-btn-field';
?>
<?php if ( $url ) : ?>
<a
	href="<?php echo esc_url( $url ); ?>"
	target="<?php echo esc_attr( $target ); ?>"
	class="<?php echo esc_attr( $cls ); ?>"
	rel="<?php echo '_blank' === $target ? 'noopener noreferrer' : ''; ?>"
>
	<?php echo wp_kses_post( $label ); ?>
</a>
<?php else : ?>
<button
	type="button"
	class="<?php echo esc_attr( $cls ); ?>"
>
	<?php echo wp_kses_post( $label ); ?>
</button>
<?php endif; ?>
