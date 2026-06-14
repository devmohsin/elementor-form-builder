<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST route: POST /clefa/v1/filter
 *
 * Accepts filter state, runs a WP_Query, renders results HTML, and returns
 * metadata (total, pages, active filters, url_params) for the JS engine.
 *
 * The actual posts HTML is rendered through a template that can be overridden
 * in a child theme at clefa-forms/filter/results.php.
 */
class CLEFA_Filter_Route {

	const NAMESPACE = 'clefa/v1';

	public function register() {
		register_rest_route( self::NAMESPACE, '/filter', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'widget_id' => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
				),
				'config'    => array(
					'type'    => 'object',
					'default' => array(),
				),
				'filter'    => array(
					'type'    => 'object',
					'default' => array(),
				),
				'page'      => array(
					'type'    => 'integer',
					'default' => 1,
					'minimum' => 1,
				),
				'orderby'   => array(
					'type'    => 'string',
					'default' => 'date',
					'sanitize_callback' => 'sanitize_key',
				),
				'order'     => array(
					'type'    => 'string',
					'default' => 'DESC',
					'enum'    => array( 'ASC', 'DESC' ),
				),
			),
		) );
	}

	public function handle( WP_REST_Request $request ) {
		$widget_config = (array) ( $request->get_param( 'config' ) ?? array() );
		$state         = CLEFA_Filter_State::from_request( $request );

		$query_args = CLEFA_Filter_Query_Builder::build( $widget_config, $state );

		/**
		 * Filter the WP_Query arguments before the filter query runs.
		 *
		 * @param array $query_args    The assembled WP_Query arguments.
		 * @param array $widget_config The filter widget configuration.
		 * @param CLEFA_Filter_State $state The current filter state.
		 */
		$query_args = apply_filters( 'clefa_filter_query_args', $query_args, $widget_config, $state );

		// Allow caching at the query level via transient
		$cache_key = 'clefa_filter_' . md5( wp_json_encode( $query_args ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$query      = new WP_Query( $query_args );
		$posts_html = $this->render_posts( $query, $widget_config );
		$pagination = $this->render_pagination( $query, $state->get_page(), $widget_config );

		$response = array(
			'success'        => true,
			'posts_html'     => $posts_html,
			'pagination_html'=> $pagination,
			'found_posts'    => $query->found_posts,
			'max_pages'      => $query->max_num_pages,
			'current_page'   => $state->get_page(),
			'active_count'   => $state->get_active_count(),
			'url_params'     => $state->to_query_string(),
			'active_filters' => $this->build_active_chips( $state, $widget_config ),
		);

		// Cache brief duration to avoid duplicate identical queries
		set_transient( $cache_key, $response, 30 );

		return rest_ensure_response( $response );
	}

	/**
	 * Render posts via the results template, falling back to a generic list.
	 */
	private function render_posts( WP_Query $query, array $widget_config ) {
		$tpl = CLEFA_Form_Renderer::locate_template( 'filter/results.php' );

		if ( $tpl ) {
			ob_start();
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( array( 'query' => $query, 'widget_config' => $widget_config ), EXTR_SKIP );
			include $tpl;
			return ob_get_clean();
		}

		// Built-in fallback: simple article list
		ob_start();
		if ( $query->have_posts() ) :
			echo '<div class="clefa-filter-results-list">';
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<article class="clefa-filter-result-item" data-post-id="<?php echo esc_attr( get_the_ID() ); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
					<a href="<?php the_permalink(); ?>" class="clefa-filter-result-thumb">
						<?php the_post_thumbnail( 'medium' ); ?>
					</a>
					<?php endif; ?>
					<div class="clefa-filter-result-body">
						<h3 class="clefa-filter-result-title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h3>
						<div class="clefa-filter-result-excerpt"><?php the_excerpt(); ?></div>
					</div>
				</article>
				<?php
			endwhile;
			echo '</div>';
		else :
			$no_results = $widget_config['no_results_text'] ?? __( 'No results found.', 'codelinden-elementor-form-addon' );
			echo '<p class="clefa-filter-no-results">' . esc_html( $no_results ) . '</p>';
		endif;
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Render pagination HTML.
	 */
	private function render_pagination( WP_Query $query, $current_page, array $widget_config ) {
		if ( $query->max_num_pages <= 1 ) { return ''; }

		$tpl = CLEFA_Form_Renderer::locate_template( 'filter/pagination.php' );
		if ( $tpl ) {
			ob_start();
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract(
				array(
					'query'         => $query,
					'current_page'  => (int) $current_page,
					'widget_config' => $widget_config,
				),
				EXTR_SKIP
			);
			include $tpl;
			return ob_get_clean();
		}

		$pages  = $query->max_num_pages;
		$type   = $widget_config['pagination_type'] ?? 'numbered'; // numbered | load_more | infinite
		$labels = array(
			'prev' => $widget_config['prev_text'] ?? __( '← Prev', 'codelinden-elementor-form-addon' ),
			'next' => $widget_config['next_text'] ?? __( 'Next →', 'codelinden-elementor-form-addon' ),
		);

		ob_start();
		if ( 'numbered' === $type ) :
			echo '<nav class="clefa-filter-pagination" data-clefa-pagination aria-label="' . esc_attr__( 'Filter results pagination', 'codelinden-elementor-form-addon' ) . '">';
			if ( $current_page > 1 ) :
				echo '<button type="button" class="clefa-page-btn clefa-page-prev" data-clefa-page="' . esc_attr( $current_page - 1 ) . '">' . esc_html( $labels['prev'] ) . '</button>';
			endif;
			for ( $i = 1; $i <= $pages; $i++ ) :
				$active = $i === (int) $current_page ? ' clefa-page-active' : '';
				echo '<button type="button" class="clefa-page-btn' . esc_attr( $active ) . '" data-clefa-page="' . esc_attr( $i ) . '" aria-current="' . ( $active ? 'page' : 'false' ) . '">' . esc_html( $i ) . '</button>';
			endfor;
			if ( $current_page < $pages ) :
				echo '<button type="button" class="clefa-page-btn clefa-page-next" data-clefa-page="' . esc_attr( $current_page + 1 ) . '">' . esc_html( $labels['next'] ) . '</button>';
			endif;
			echo '</nav>';
		elseif ( 'load_more' === $type && $current_page < $pages ) :
			$load_text = $widget_config['load_more_text'] ?? __( 'Load More', 'codelinden-elementor-form-addon' );
			echo '<div class="clefa-filter-load-more-wrap"><button type="button" class="clefa-btn clefa-btn-secondary" data-clefa-load-more data-clefa-page="' . esc_attr( $current_page + 1 ) . '">' . esc_html( $load_text ) . '</button></div>';
		endif;
		return ob_get_clean();
	}

	/**
	 * Build active filter chip data for the JS engine.
	 *
	 * @return array  [ { section_id, label, value_label } ]
	 */
	private function build_active_chips( CLEFA_Filter_State $state, array $widget_config ) {
		$chips    = array();
		$sections = array();
		foreach ( ( $widget_config['filter_sections'] ?? array() ) as $s ) {
			$sections[ $s['section_id'] ?? '' ] = $s;
		}

		foreach ( $state->get_all() as $sid => $val ) {
			if ( ! $state->is_active( $sid ) ) { continue; }
			$section     = $sections[ $sid ] ?? array();
			$label       = sanitize_text_field( $section['section_label'] ?? $sid );
			$value_label = is_array( $val ) ? implode( ', ', $val ) : (string) $val;
			$chips[]     = array(
				'section_id'  => $sid,
				'label'       => $label,
				'value_label' => $value_label,
			);
		}
		return $chips;
	}
}
