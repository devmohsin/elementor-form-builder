<?php
/**
 * Field template: html / rich text block
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 *
 * Tokens in the form {field_id} are wrapped in <span data-clefa-token="field_id">
 * so that FormEngine.js can replace them live as users fill in other fields.
 *
 * Example content:  "Welcome, {first_name}! Your email is {email}."
 * Renders as HTML:  "Welcome, <span data-clefa-token="first_name">{first_name}</span>! ..."
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$content = $field['content'] ?? '';

// Wrap {token} placeholders in targetable spans for live JS replacement.
if ( $content && preg_match( '/\{[a-zA-Z0-9_\-]+\}/', $content ) ) {
	$content = preg_replace_callback(
		'/\{([a-zA-Z0-9_\-]+)\}/',
		function ( $m ) {
			return '<span data-clefa-token="' . esc_attr( $m[1] ) . '">' . esc_html( $m[0] ) . '</span>';
		},
		$content
	);
}
?>
<?php if ( $content ) : ?>
<div class="clefa-html-block" data-clefa-html-block>
	<?php
	// Content already processed; allow span with data attribute.
	$kses_args = array_merge(
		wp_kses_allowed_html( 'post' ),
		array( 'span' => array( 'data-clefa-token' => true, 'class' => true, 'id' => true ) )
	);
	echo wp_kses( $content, $kses_args );
	?>
</div>
<?php endif; ?>
