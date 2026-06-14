<?php
/**
 * Filter section: search (full-text keyword)
 * Variables: $section, $sec_id, $options
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$placeholder = esc_attr( $section['placeholder'] ?? __( 'Search…', 'codelinden-elementor-form-addon' ) );
?>
<div class="clefa-filter-search-wrap">
	<input
		type="search"
		name="clefa_filter[<?php echo esc_attr( $sec_id ); ?>]"
		class="clefa-input clefa-filter-search"
		data-clefa-filter-input="<?php echo esc_attr( $sec_id ); ?>"
		placeholder="<?php echo $placeholder; ?>"
		aria-label="<?php echo $placeholder; ?>"
	/>
</div>
