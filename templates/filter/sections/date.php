<?php
/**
 * Filter section: date range
 * Variables: $section, $sec_id, $options
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$label_from = esc_html( $section['date_from_label'] ?? __( 'From', 'codelinden-elementor-form-addon' ) );
$label_to   = esc_html( $section['date_to_label']   ?? __( 'To',   'codelinden-elementor-form-addon' ) );
?>
<div class="clefa-filter-date-range" data-clefa-filter-input="<?php echo esc_attr( $sec_id ); ?>">
	<div class="clefa-filter-date-row">
		<label class="clefa-filter-date-label" for="clefa-fd-from-<?php echo esc_attr( $sec_id ); ?>">
			<?php echo $label_from; ?>
		</label>
		<input
			type="date"
			id="clefa-fd-from-<?php echo esc_attr( $sec_id ); ?>"
			name="clefa_filter[<?php echo esc_attr( $sec_id ); ?>][from]"
			class="clefa-input clefa-filter-date-from"
			data-clefa-filter-date="from"
		/>
	</div>
	<div class="clefa-filter-date-row">
		<label class="clefa-filter-date-label" for="clefa-fd-to-<?php echo esc_attr( $sec_id ); ?>">
			<?php echo $label_to; ?>
		</label>
		<input
			type="date"
			id="clefa-fd-to-<?php echo esc_attr( $sec_id ); ?>"
			name="clefa_filter[<?php echo esc_attr( $sec_id ); ?>][to]"
			class="clefa-input clefa-filter-date-to"
			data-clefa-filter-date="to"
		/>
	</div>
</div>
