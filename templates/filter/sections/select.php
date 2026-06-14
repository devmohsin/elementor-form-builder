<?php
/**
 * Filter section: select
 * Variables: $section, $sec_id, $options
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$placeholder = esc_html( $section['placeholder'] ?? __( '— All —', 'codelinden-elementor-form-addon' ) );
?>
<select
	name="clefa_filter[<?php echo esc_attr( $sec_id ); ?>]"
	class="clefa-filter-select clefa-input"
	data-clefa-filter-input="<?php echo esc_attr( $sec_id ); ?>"
>
	<option value=""><?php echo $placeholder; ?></option>
	<?php foreach ( $options as $opt ) :
		$val   = esc_attr( $opt['value'] ?? '' );
		$label = esc_html( $opt['label'] ?? $val );
	?>
	<option value="<?php echo $val; ?>"><?php echo $label; ?></option>
	<?php endforeach; ?>
</select>
