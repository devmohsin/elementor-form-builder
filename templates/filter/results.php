<?php
/**
 * Filter results template.
 *
 * This file is loaded by CLEFA_Filter_Route::render_posts() when a child theme
 * (or parent theme) provides an override at:
 *   clefa-forms/filter/results.php
 *
 * Available variables:
 *   $query         WP_Query   – The executed query. Use have_posts() / the_post() as normal.
 *   $widget_config array      – The Elementor filter widget settings array.
 *
 * You can copy this file to your (child) theme at the path above and customise
 * the markup freely. The plugin's built-in fallback in Filter_Route.php is used
 * when no override exists.
 *
 * Notes:
 *   - Call wp_reset_postdata() after the loop.
 *   - Wrap each result in an <article> with [data-post-id] so the FilterEngine's
 *     JS event delegation can dispatch clefa:filter:result:click.
 *   - Wrap all results in a [data-clefa-posts] element so FilterEngine can target
 *     only the posts area for partial HTML replacement.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$no_results_text = $widget_config['no_results_text'] ?? __( 'No results found.', 'codelinden-elementor-form-addon' );
?>
<div class="clefa-filter-results" data-clefa-posts>
<?php if ( $query->have_posts() ) : ?>
	<div class="clefa-filter-results-list">
	<?php while ( $query->have_posts() ) : $query->the_post(); ?>
		<article
			class="clefa-filter-result-item"
			data-post-id="<?php echo esc_attr( get_the_ID() ); ?>"
		>
			<?php if ( has_post_thumbnail() ) : ?>
			<a
				href="<?php the_permalink(); ?>"
				class="clefa-filter-result-thumb"
				tabindex="-1"
				aria-hidden="true"
			>
				<?php the_post_thumbnail( 'medium' ); ?>
			</a>
			<?php endif; ?>

			<div class="clefa-filter-result-body">
				<h3 class="clefa-filter-result-title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h3>
				<div class="clefa-filter-result-meta">
					<time
						class="clefa-filter-result-date"
						datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"
					>
						<?php echo esc_html( get_the_date() ); ?>
					</time>
					<?php if ( has_category() ) : ?>
					<span class="clefa-filter-result-cats">
						<?php the_category( ', ' ); ?>
					</span>
					<?php endif; ?>
				</div>
				<div class="clefa-filter-result-excerpt">
					<?php the_excerpt(); ?>
				</div>
				<a
					href="<?php the_permalink(); ?>"
					class="clefa-btn clefa-btn-link clefa-filter-result-readmore"
				>
					<?php echo esc_html( $widget_config['read_more_text'] ?? __( 'Read more', 'codelinden-elementor-form-addon' ) ); ?>
					<span class="screen-reader-text">
						<?php /* translators: %s: post title */ ?>
						<?php printf( esc_html__( ' about %s', 'codelinden-elementor-form-addon' ), get_the_title() ); ?>
					</span>
				</a>
			</div>
		</article>
	<?php endwhile; ?>
	</div>
<?php else : ?>
	<p class="clefa-filter-no-results">
		<?php echo esc_html( $no_results_text ); ?>
	</p>
<?php endif; ?>
<?php wp_reset_postdata(); ?>
</div>
