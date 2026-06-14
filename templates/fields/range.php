<?php
/**
 * Field template: range / slider
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id  = $field['field_id']              ?? '';
$has_error = ! empty( $errors[ $field_id ] );
$min       = $field['validation']['min_value'] ?? ( $field['min'] ?? 0 );
$max       = $field['validation']['max_value'] ?? ( $field['max'] ?? 100 );
$step      = $field['step']                   ?? 1;
$default   = '' !== $value ? $value : ( $field['default_value'] ?? $min );
$prefix    = $field['prefix']                 ?? '';
$suffix    = $field['suffix']                 ?? '';
$input_id  = 'clefa-field-' . esc_attr( $field_id );

// Percentage filled for the initial gradient
$range     = ( (float) $max - (float) $min );
$pct       = $range > 0 ? round( ( ( (float) $default - (float) $min ) / $range ) * 100 ) : 0;
?>
<div class="clefa-range-wrap" data-clefa-range-wrap>

	<div class="clefa-range-header">
		<span class="clefa-range-minmax clefa-range-minmax-min">
			<?php echo esc_html( $prefix . $min . $suffix ); ?>
		</span>

		<span class="clefa-range-current-wrap">
			<?php if ( $prefix ) : ?>
				<span class="clefa-range-prefix"><?php echo esc_html( $prefix ); ?></span>
			<?php endif; ?>
			<output
				class="clefa-range-value"
				data-clefa-range-output
				for="<?php echo $input_id; ?>"
			><?php echo esc_html( (string) $default ); ?></output>
			<?php if ( $suffix ) : ?>
				<span class="clefa-range-suffix"><?php echo esc_html( $suffix ); ?></span>
			<?php endif; ?>
		</span>

		<span class="clefa-range-minmax clefa-range-minmax-max">
			<?php echo esc_html( $prefix . $max . $suffix ); ?>
		</span>
	</div>

	<input
		type="range"
		id="<?php echo $input_id; ?>"
		name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
		data-clefa-input
		data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
		value="<?php echo esc_attr( (string) $default ); ?>"
		class="clefa-range<?php echo $has_error ? ' clefa-input-error' : ''; ?>"
		min="<?php echo esc_attr( $min ); ?>"
		max="<?php echo esc_attr( $max ); ?>"
		step="<?php echo esc_attr( $step ); ?>"
		style="--clefa-range-pct:<?php echo $pct; ?>%"
		<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
		<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
	/>

</div>
