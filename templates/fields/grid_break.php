<?php
/**
 * Grid / Column Break field template.
 *
 * This field does not submit any data. Its sole purpose is layout control:
 * it closes the current CSS grid context and opens a new one (or ends it).
 *
 * Variables available from CLEFA_Form_Renderer::render_field():
 * @var array $field    Field config array.
 * @var array $form     Form config array.
 * @var string $step_id Current step ID.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$sub_type = $field['grid_sub_type'] ?? 'start';   // 'start' | 'end' | 'break'
$cols     = absint( $field['grid_columns'] ?? 2 );
$cols     = max( 1, min( $cols, 6 ) );
$gap      = sanitize_text_field( $field['grid_gap'] ?? '' );   // e.g. '16px'
$cls      = sanitize_text_field( $field['custom_class'] ?? '' );
$attrs    = '';

if ( ! empty( $field['custom_attributes'] ) ) {
	foreach ( (array) $field['custom_attributes'] as $attr ) {
		$key = sanitize_key( $attr['key'] ?? '' );
		$val = esc_attr( $attr['value'] ?? '' );
		if ( $key ) { $attrs .= ' ' . $key . '="' . $val . '"'; }
	}
}

if ( 'end' === $sub_type ) :
?>
</div><!-- /.clefa-grid-wrap -->
<?php elseif ( 'break' === $sub_type ) : ?>
</div><!-- /.clefa-grid-wrap (break) -->
<div class="clefa-grid-wrap <?php echo esc_attr( $cls ); ?>"
     data-clefa-grid
     data-clefa-grid-cols="<?php echo esc_attr( $cols ); ?>"
     <?php if ( $gap ) { echo 'style="gap:' . esc_attr( $gap ) . '"'; } ?>
     <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
<?php else : // start ?>
<div class="clefa-grid-wrap <?php echo esc_attr( $cls ); ?>"
     data-clefa-grid
     data-clefa-grid-cols="<?php echo esc_attr( $cols ); ?>"
     <?php if ( $gap ) { echo 'style="gap:' . esc_attr( $gap ) . '"'; } ?>
     <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
<?php endif; ?>
