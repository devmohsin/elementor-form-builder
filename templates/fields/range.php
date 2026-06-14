<?php
/**
 * Field template: range / slider
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id = $field['field_id']    ?? '';
$has_error= ! empty( $errors[ $field_id ] );
$min      = $field['validation']['min_value'] ?? 0;
$max      = $field['validation']['max_value'] ?? 100;
$step     = $field['step'] ?? 1;
$default  = '' !== $value ? $value : ( $field['default_value'] ?? $min );
$show_val = ! empty( $field['show_value'] );
?>
<div class="clefa-range-wrap" data-clefa-range-wrap>
	<input
		type="range"
		id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
		name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
		data-clefa-input
		data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
		value="<?php echo esc_attr( (string) $default ); ?>"
		class="clefa-range<?php echo $has_error ? ' clefa-input-error' : ''; ?>"
		min="<?php echo esc_attr( $min ); ?>"
		max="<?php echo esc_attr( $max ); ?>"
		step="<?php echo esc_attr( $step ); ?>"
		<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
		<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
	/>
	<?php if ( $show_val ) : ?>
	<output
		class="clefa-range-value"
		data-clefa-range-output
		for="clefa-field-<?php echo esc_attr( $field_id ); ?>"
	><?php echo esc_html( (string) $default ); ?></output>
	<?php endif; ?>
</div>
