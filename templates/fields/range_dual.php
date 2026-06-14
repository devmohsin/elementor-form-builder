<?php
/**
 * Field template: range_dual — dual-handle min/max range slider.
 *
 * Uses two overlapping <input type="range"> elements + vanilla JS.
 * No jQuery or external library required.
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id  = $field['field_id'] ?? '';
$has_error = ! empty( $errors[ $field_id ] );
$min       = (float) ( $field['validation']['min_value'] ?? $field['min'] ?? 0 );
$max       = (float) ( $field['validation']['max_value'] ?? $field['max'] ?? 100 );
$step      = (float) ( $field['step'] ?? 1 );
$prefix    = esc_html( $field['prefix'] ?? '' );
$suffix    = esc_html( $field['suffix'] ?? '' );

// Value may be a two-element array [min, max] or a single string 'min,max'
if ( is_array( $value ) ) {
	$val_min = (float) ( $value[0] ?? $min );
	$val_max = (float) ( $value[1] ?? $max );
} elseif ( strpos( (string) $value, ',' ) !== false ) {
	$parts   = explode( ',', (string) $value, 2 );
	$val_min = (float) trim( $parts[0] );
	$val_max = (float) trim( $parts[1] );
} else {
	$val_min = $min;
	$val_max = $max;
}

// Clamp to bounds
$val_min = max( $min, min( $val_min, $max ) );
$val_max = max( $min, min( $val_max, $max ) );
if ( $val_min > $val_max ) { $val_min = $val_max; }
?>
<div
	class="clefa-range-dual<?php echo $has_error ? ' clefa-input-error' : ''; ?>"
	data-clefa-range-dual
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	data-min="<?php echo esc_attr( $min ); ?>"
	data-max="<?php echo esc_attr( $max ); ?>"
	data-step="<?php echo esc_attr( $step ); ?>"
>
	<div class="clefa-range-dual-track" aria-hidden="true">
		<div class="clefa-range-dual-fill" data-clefa-range-dual-fill></div>
	</div>

	<input
		type="range"
		class="clefa-range-dual-input clefa-range-dual-min"
		data-clefa-range-dual-input="min"
		min="<?php echo esc_attr( $min ); ?>"
		max="<?php echo esc_attr( $max ); ?>"
		step="<?php echo esc_attr( $step ); ?>"
		value="<?php echo esc_attr( $val_min ); ?>"
		aria-label="<?php esc_attr_e( 'Minimum value', 'codelinden-elementor-form-addon' ); ?>"
		<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
	/>
	<input
		type="range"
		class="clefa-range-dual-input clefa-range-dual-max"
		data-clefa-range-dual-input="max"
		min="<?php echo esc_attr( $min ); ?>"
		max="<?php echo esc_attr( $max ); ?>"
		step="<?php echo esc_attr( $step ); ?>"
		value="<?php echo esc_attr( $val_max ); ?>"
		aria-label="<?php esc_attr_e( 'Maximum value', 'codelinden-elementor-form-addon' ); ?>"
	/>

	<div class="clefa-range-dual-labels">
		<span class="clefa-range-dual-label-min">
			<?php if ( $prefix ) : ?><span class="clefa-range-prefix"><?php echo $prefix; ?></span><?php endif; ?>
			<span data-clefa-range-dual-value="min"><?php echo esc_html( $val_min ); ?></span>
			<?php if ( $suffix ) : ?><span class="clefa-range-suffix"><?php echo $suffix; ?></span><?php endif; ?>
		</span>
		<span class="clefa-range-dual-sep">&ndash;</span>
		<span class="clefa-range-dual-label-max">
			<?php if ( $prefix ) : ?><span class="clefa-range-prefix"><?php echo $prefix; ?></span><?php endif; ?>
			<span data-clefa-range-dual-value="max"><?php echo esc_html( $val_max ); ?></span>
			<?php if ( $suffix ) : ?><span class="clefa-range-suffix"><?php echo $suffix; ?></span><?php endif; ?>
		</span>
	</div>

	<?php /* Hidden inputs carry the real field values for form serialization */ ?>
	<input
		type="hidden"
		id="clefa-field-<?php echo esc_attr( $field_id ); ?>-min"
		name="clefa_field[<?php echo esc_attr( $field_id ); ?>][0]"
		data-clefa-input
		data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
		value="<?php echo esc_attr( $val_min ); ?>"
	/>
	<input
		type="hidden"
		id="clefa-field-<?php echo esc_attr( $field_id ); ?>-max"
		name="clefa_field[<?php echo esc_attr( $field_id ); ?>][1]"
		value="<?php echo esc_attr( $val_max ); ?>"
	/>
</div>
