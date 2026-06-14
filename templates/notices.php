<?php
/**
 * Form message / notice template.
 *
 * Rendered by JavaScript (FormEngine.js) after a successful or failed
 * submission by injecting HTML into the [data-clefa-message] container.
 * This template produces the *server-side* fallback HTML that is returned
 * inside the REST response's `message_html` key. The JS engine inserts it
 * verbatim into the message container.
 *
 * Child themes can override this file at:
 *   clefa-forms/notices.php
 *
 * Available variables:
 *   $type     string   'success' | 'error' | 'info' | 'warning'
 *   $message  string   The notice text (already sanitised / kses'd by caller).
 *   $form_id  int      The form ID.
 *
 * Notes:
 *   - Use [data-clefa-notice] on the root element so the FormEngine can
 *     find and dismiss it when the form is reset.
 *   - For success notices the JS also hides the form when
 *     data-clefa-hide-on-success="1" is set on the form wrapper.
 *   - Avoid inline styles; use CSS classes and the form-engine.css
 *     variables (--clefa-primary, --clefa-error, etc.) instead.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$type_class = 'clefa-notice-' . sanitize_html_class( $type ?? 'info' );
?>
<div
	class="clefa-notice <?php echo $type_class; ?>"
	data-clefa-notice
	role="<?php echo ( 'error' === ( $type ?? '' ) ) ? 'alert' : 'status'; ?>"
	aria-live="<?php echo ( 'error' === ( $type ?? '' ) ) ? 'assertive' : 'polite'; ?>"
>
	<span class="clefa-notice-icon" aria-hidden="true"></span>
	<div class="clefa-notice-body">
		<?php echo wp_kses_post( $message ?? '' ); ?>
	</div>
	<button
		type="button"
		class="clefa-notice-dismiss"
		data-clefa-notice-dismiss
		aria-label="<?php esc_attr_e( 'Dismiss notice', 'codelinden-elementor-form-addon' ); ?>"
	>
		<span aria-hidden="true">&times;</span>
	</button>
</div>
