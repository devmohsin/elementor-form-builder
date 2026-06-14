<?php
/**
 * Field template: number
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id    = $field['field_id']    ?? '';
$placeholder = $field['placeholder'] ?? '';
$min         = $field['validation']['min_value'] ?? '';
$max         = $field['validation']['max_value'] ?? '';
$step        = $field['step'] ?? '';
$has_error   = ! empty( $errors[ $field_id ] );
?>
<input
	type="number"
	id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
	name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
	data-clefa-input
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	value="<?php echo esc_attr( '' !== $value ? (string) $value : '' ); ?>"
	class="clefa-input clefa-input-number<?php echo $has_error ? ' clefa-input-error' : ''; ?>"
	placeholder="<?php echo esc_attr( $placeholder ); ?>"
	<?php if ( '' !== $min ) : ?>min="<?php echo esc_attr( $min ); ?>"<?php endif; ?>
	<?php if ( '' !== $max ) : ?>max="<?php echo esc_attr( $max ); ?>"<?php endif; ?>
	<?php if ( '' !== $step ) : ?>step="<?php echo esc_attr( $step ); ?>"<?php endif; ?>
	<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
	<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
/>
