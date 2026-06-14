<?php
/**
 * Filter section: range_dual (min/max dual-handle range slider).
 * Variables: $section, $sec_id, $options (unused for range)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$min    = (float) ( $section['range_min'] ?? 0 );
$max    = (float) ( $section['range_max'] ?? 1000 );
$step   = (float) ( $section['range_step'] ?? 1 );
$prefix = esc_html( $section['range_prefix'] ?? '' );
$suffix = esc_html( $section['range_suffix'] ?? '' );
?>
<div
	class="clefa-range-dual clefa-filter-range-dual"
	data-clefa-range-dual
	data-clefa-filter-input="<?php echo esc_attr( $sec_id ); ?>"
	data-min="<?php echo esc_attr( $min ); ?>"
	data-max="<?php echo esc_attr( $max ); ?>"
	data-step="<?php echo esc_attr( $step ); ?>"
>
	<div class="clefa-range-dual-track" aria-hidden="true">
		<div class="clefa-range-dual-fill" data-clefa-range-dual-fill></div>
	</div>

	<input type="range" class="clefa-range-dual-input clefa-range-dual-min" data-clefa-range-dual-input="min"
		min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $min ); ?>"
		aria-label="<?php esc_attr_e( 'Minimum', 'codelinden-elementor-form-addon' ); ?>" />
	<input type="range" class="clefa-range-dual-input clefa-range-dual-max" data-clefa-range-dual-input="max"
		min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $max ); ?>"
		aria-label="<?php esc_attr_e( 'Maximum', 'codelinden-elementor-form-addon' ); ?>" />

	<div class="clefa-range-dual-labels">
		<span class="clefa-range-dual-label-min">
			<?php if ( $prefix ) echo '<span class="clefa-range-prefix">' . $prefix . '</span>'; ?>
			<span data-clefa-range-dual-value="min"><?php echo esc_html( $min ); ?></span>
			<?php if ( $suffix ) echo '<span class="clefa-range-suffix">' . $suffix . '</span>'; ?>
		</span>
		<span class="clefa-range-dual-sep">&ndash;</span>
		<span class="clefa-range-dual-label-max">
			<?php if ( $prefix ) echo '<span class="clefa-range-prefix">' . $prefix . '</span>'; ?>
			<span data-clefa-range-dual-value="max"><?php echo esc_html( $max ); ?></span>
			<?php if ( $suffix ) echo '<span class="clefa-range-suffix">' . $suffix . '</span>'; ?>
		</span>
	</div>

	<input type="hidden" name="clefa_filter[<?php echo esc_attr( $sec_id ); ?>][min]" data-clefa-range-hidden="min" value="<?php echo esc_attr( $min ); ?>">
	<input type="hidden" name="clefa_filter[<?php echo esc_attr( $sec_id ); ?>][max]" data-clefa-range-hidden="max" value="<?php echo esc_attr( $max ); ?>">
</div>
