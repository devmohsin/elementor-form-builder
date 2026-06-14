<?php
/**
 * Field template: password
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id     = $field['field_id']    ?? '';
$placeholder  = $field['placeholder'] ?? '';
$has_error    = ! empty( $errors[ $field_id ] );
$show_toggle  = ! empty( $field['show_toggle'] );
?>
<div class="clefa-password-wrap" data-clefa-password-wrap>
	<input
		type="password"
		id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
		name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
		data-clefa-input
		data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
		value=""
		class="clefa-input clefa-input-password<?php echo $has_error ? ' clefa-input-error' : ''; ?>"
		placeholder="<?php echo esc_attr( $placeholder ); ?>"
		autocomplete="<?php echo esc_attr( $field['autocomplete'] ?? 'current-password' ); ?>"
		<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
		<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
	/>
	<?php if ( $show_toggle ) : ?>
	<button
		type="button"
		class="clefa-password-toggle"
		data-clefa-password-toggle
		aria-label="<?php esc_attr_e( 'Toggle password visibility', 'codelinden-elementor-form-addon' ); ?>"
		aria-pressed="false"
	>
		<span data-clefa-pw-show><?php esc_html_e( 'Show', 'codelinden-elementor-form-addon' ); ?></span>
		<span data-clefa-pw-hide style="display:none"><?php esc_html_e( 'Hide', 'codelinden-elementor-form-addon' ); ?></span>
	</button>
	<?php endif; ?>
</div>
