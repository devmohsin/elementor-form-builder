<?php
/**
 * Filter section: radio
 * Variables: $section, $sec_id, $options
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<ul class="clefa-filter-options clefa-filter-radios" role="group">
	<?php foreach ( $options as $opt ) :
		$val   = esc_attr( $opt['value'] ?? '' );
		$label = esc_html( $opt['label'] ?? $val );
		$count = $opt['count'] !== null ? ' <span class="clefa-filter-count">(' . absint( $opt['count'] ) . ')</span>' : '';
		$uid   = 'clefa-rf-' . $sec_id . '-' . sanitize_key( $opt['value'] ?? '' );
	?>
	<li class="clefa-filter-option">
		<input
			type="radio"
			id="<?php echo esc_attr( $uid ); ?>"
			name="clefa_filter[<?php echo esc_attr( $sec_id ); ?>]"
			value="<?php echo $val; ?>"
			class="clefa-filter-radio"
			data-clefa-filter-input="<?php echo esc_attr( $sec_id ); ?>"
		/>
		<label for="<?php echo esc_attr( $uid ); ?>" class="clefa-filter-option-label">
			<?php echo $label; ?>
			<?php echo $count; // phpcs:ignore ?>
		</label>
	</li>
	<?php endforeach; ?>
</ul>
