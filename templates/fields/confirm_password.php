<?php
/**
 * Field template: confirm_password
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id    = $field['field_id']    ?? '';
$placeholder = $field['placeholder'] ?? '';
$main_field  = $field['validation']['main_password_field'] ?? '';
$has_error   = ! empty( $errors[ $field_id ] );
?>
<div class="clefa-password-wrap" data-clefa-password-wrap>
	<input
		type="password"
		id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
		name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
		data-clefa-input
		data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
		data-clefa-confirm-for="<?php echo esc_attr( $main_field ); ?>"
		value=""
		class="clefa-input clefa-input-password clefa-input-confirm<?php echo $has_error ? ' clefa-input-error' : ''; ?>"
		placeholder="<?php echo esc_attr( $placeholder ); ?>"
		autocomplete="new-password"
		<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
		<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
	/>
</div>
