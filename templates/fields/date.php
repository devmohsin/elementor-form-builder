<?php
/**
 * Field template: date
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id  = $field['field_id'] ?? '';
$has_error = ! empty( $errors[ $field_id ] );
$min_date  = $field['validation']['min_date'] ?? '';
$max_date  = $field['validation']['max_date'] ?? '';
?>
<input
	type="date"
	id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
	name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
	data-clefa-input
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	value="<?php echo esc_attr( (string) $value ); ?>"
	class="clefa-input clefa-input-date<?php echo $has_error ? ' clefa-input-error' : ''; ?>"
	<?php if ( $min_date ) : ?>min="<?php echo esc_attr( $min_date ); ?>"<?php endif; ?>
	<?php if ( $max_date ) : ?>max="<?php echo esc_attr( $max_date ); ?>"<?php endif; ?>
	<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
	<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
	<?php if ( ! empty( $field['validation']['age_over_18'] ) ) : ?>
		data-clefa-age-check="18"
	<?php elseif ( ! empty( $field['validation']['age_over'] ) ) : ?>
		data-clefa-age-check="<?php echo esc_attr( $field['validation']['age_over'] ); ?>"
	<?php endif; ?>
/>
