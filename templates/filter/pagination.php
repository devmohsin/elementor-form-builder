<?php
/**
 * Filter pagination template.
 *
 * Rendered by CLEFA_Filter_Route::render_pagination() when child themes
 * provide an override at:
 *   clefa-forms/filter/pagination.php
 *
 * Available variables:
 *   $query          WP_Query   The executed query.
 *   $current_page   int        Current page number (1-based).
 *   $widget_config  array      The Elementor filter widget settings array.
 *
 * Pagination type choices ('numbered' | 'load_more' | 'infinite') are read
 * from $widget_config['pagination_type']. Only 'numbered' and 'load_more'
 * need markup — 'infinite' is handled entirely in JavaScript.
 *
 * JS data attributes consumed by FilterEngine.js:
 *   [data-clefa-pagination]        — wrapper, watched for delegated page clicks.
 *   [data-clefa-page="N"]          — fires a filter request for page N.
 *   [data-clefa-load-more]         — triggers append mode for the next page.
 *
 * Copy this file to your (child) theme at the path above to customise the
 * pagination markup. The plugin's built-in fallback in Filter_Route.php is
 * used when no override exists.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( $query->max_num_pages <= 1 ) { return; }

$pages    = (int) $query->max_num_pages;
$type     = $widget_config['pagination_type'] ?? 'numbered';
$prev_text = $widget_config['prev_text']      ?? __( '← Prev', 'codelinden-elementor-form-addon' );
$next_text = $widget_config['next_text']      ?? __( 'Next →', 'codelinden-elementor-form-addon' );
?>
<?php if ( 'numbered' === $type ) : ?>
<nav
	class="clefa-filter-pagination"
	data-clefa-pagination
	aria-label="<?php esc_attr_e( 'Filter results pagination', 'codelinden-elementor-form-addon' ); ?>"
>
	<?php if ( $current_page > 1 ) : ?>
	<button
		type="button"
		class="clefa-page-btn clefa-page-prev"
		data-clefa-page="<?php echo esc_attr( $current_page - 1 ); ?>"
		aria-label="<?php echo esc_attr( $prev_text ); ?>"
	>
		<?php echo esc_html( $prev_text ); ?>
	</button>
	<?php endif; ?>

	<?php for ( $i = 1; $i <= $pages; $i++ ) :
		$is_active = $i === (int) $current_page;
	?>
	<button
		type="button"
		class="clefa-page-btn<?php echo $is_active ? ' clefa-page-active' : ''; ?>"
		data-clefa-page="<?php echo esc_attr( $i ); ?>"
		aria-current="<?php echo $is_active ? 'page' : 'false'; ?>"
		<?php if ( $is_active ) : ?>aria-disabled="true"<?php endif; ?>
	>
		<?php echo esc_html( $i ); ?>
	</button>
	<?php endfor; ?>

	<?php if ( $current_page < $pages ) : ?>
	<button
		type="button"
		class="clefa-page-btn clefa-page-next"
		data-clefa-page="<?php echo esc_attr( $current_page + 1 ); ?>"
		aria-label="<?php echo esc_attr( $next_text ); ?>"
	>
		<?php echo esc_html( $next_text ); ?>
	</button>
	<?php endif; ?>
</nav>

<?php elseif ( 'load_more' === $type && $current_page < $pages ) :
	$load_text = $widget_config['load_more_text'] ?? __( 'Load More', 'codelinden-elementor-form-addon' );
?>
<div class="clefa-filter-load-more-wrap">
	<button
		type="button"
		class="clefa-btn clefa-btn-secondary clefa-load-more"
		data-clefa-load-more
		data-clefa-page="<?php echo esc_attr( $current_page + 1 ); ?>"
	>
		<?php echo esc_html( $load_text ); ?>
	</button>
</div>
<?php endif; ?>
