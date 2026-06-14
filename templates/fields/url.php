<?php
/**
 * Field template: url
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id    = $field['field_id']    ?? '';
$placeholder = $field['placeholder'] ?? 'https://';
$has_error   = ! empty( $errors[ $field_id ] );
?>
<input
	type="url"
	id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
	name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
	data-clefa-input
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	value="<?php echo esc_attr( esc_url( (string) $value ) ); ?>"
	class="clefa-input clefa-input-url<?php echo $has_error ? ' clefa-input-error' : ''; ?>"
	placeholder="<?php echo esc_attr( $placeholder ); ?>"
	autocomplete="url"
	<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
	<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
/>
