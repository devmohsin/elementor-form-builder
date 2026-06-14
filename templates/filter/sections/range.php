<?php
/**
 * Filter section: range (single-handle range slider).
 * Variables: $section, $sec_id, $options (unused for range)
 *
 * Emits data-clefa-filter-input so the FilterEngine picks up the
 * value and sends it as `clefa_filter[sec_id]` in the AJAX payload.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$min     = (float) ( $section['range_min']    ?? 0 );
$max     = (float) ( $section['range_max']    ?? 100 );
$step    = (float) ( $section['range_step']   ?? 1 );
$default = (float) ( $section['range_default'] ?? $min );
$prefix  = esc_html( $section['range_prefix'] ?? '' );
$suffix  = esc_html( $section['range_suffix'] ?? '' );
?>
<div
	class="clefa-filter-range-wrap"
	data-clefa-filter-range
	data-min="<?php echo esc_attr( $min ); ?>"
	data-max="<?php echo esc_attr( $max ); ?>"
	data-step="<?php echo esc_attr( $step ); ?>"
>
	<div class="clefa-filter-range-track" aria-hidden="true">
		<div class="clefa-filter-range-fill" data-clefa-filter-range-fill></div>
	</div>

	<input
		type="range"
		class="clefa-input clefa-filter-range-input"
		data-clefa-filter-input="<?php echo esc_attr( $sec_id ); ?>"
		name="clefa_filter[<?php echo esc_attr( $sec_id ); ?>]"
		min="<?php echo esc_attr( $min ); ?>"
		max="<?php echo esc_attr( $max ); ?>"
		step="<?php echo esc_attr( $step ); ?>"
		value="<?php echo esc_attr( $default ); ?>"
		aria-label="<?php echo esc_attr( $section['label'] ?? __( 'Range', 'codelinden-elementor-form-addon' ) ); ?>"
	/>

	<div class="clefa-filter-range-value" aria-live="polite">
		<?php if ( $prefix ) : ?>
			<span class="clefa-range-prefix"><?php echo $prefix; ?></span>
		<?php endif; ?>
		<span data-clefa-filter-range-value><?php echo esc_html( $default ); ?></span>
		<?php if ( $suffix ) : ?>
			<span class="clefa-range-suffix"><?php echo $suffix; ?></span>
		<?php endif; ?>
	</div>
</div>
