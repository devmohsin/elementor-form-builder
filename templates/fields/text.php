<?php
/**
 * Field template: text (also used for phone, url via $field['field_type'])
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id    = $field['field_id']    ?? '';
$input_type  = $field['field_type']  ?? 'text';
$placeholder = $field['placeholder'] ?? '';
$max_length  = $field['validation']['max_length'] ?? '';
$min_length  = $field['validation']['min_length'] ?? '';
$has_error   = ! empty( $errors[ $field_id ] );
$input_type  = in_array( $input_type, array( 'text', 'phone', 'url' ), true ) ? $input_type : 'text';
?>
<input
	type="<?php echo esc_attr( $input_type ); ?>"
	id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
	name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
	data-clefa-input
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	value="<?php echo esc_attr( (string) $value ); ?>"
	class="clefa-input<?php echo $has_error ? ' clefa-input-error' : ''; ?>"
	placeholder="<?php echo esc_attr( $placeholder ); ?>"
	<?php if ( $max_length ) : ?>maxlength="<?php echo esc_attr( $max_length ); ?>"<?php endif; ?>
	<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
	<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
	<?php if ( ! empty( $field['readonly'] ) ) : ?>readonly<?php endif; ?>
	<?php if ( ! empty( $field['disabled'] ) ) : ?>disabled<?php endif; ?>
	<?php if ( ! empty( $field['autocomplete'] ) ) : ?>autocomplete="<?php echo esc_attr( $field['autocomplete'] ); ?>"<?php endif; ?>
/>
