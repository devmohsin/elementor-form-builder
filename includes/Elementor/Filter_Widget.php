<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
	return;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Repeater;

class CLEFA_Filter_Widget extends Widget_Base {

	public function get_name() {
		return 'clefa-filter';
	}

	public function get_title() {
		return esc_html__( 'Filter Sidebar', 'codelinden-elementor-form-addon' );
	}

	public function get_icon() {
		return 'eicon-filter';
	}

	public function get_categories() {
		return array( 'codelinden-elements' );
	}

	public function get_keywords() {
		return array( 'filter', 'sidebar', 'search', 'query', 'facet', 'clefa' );
	}

	/* ------------------------------------------------------------------ */
	/* Controls                                                              */
	/* ------------------------------------------------------------------ */

	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	private function register_content_controls() {

		/* ---- Query Settings ---- */
		$this->start_controls_section( 'section_query', array(
			'label' => esc_html__( 'Query', 'codelinden-elementor-form-addon' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'filter_post_type', array(
			'label'   => esc_html__( 'Post Type', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'post',
			'options' => $this->get_post_types(),
		) );

		$this->add_control( 'posts_per_page', array(
			'label'   => esc_html__( 'Posts Per Page', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 9,
			'min'     => 1,
			'max'     => 100,
		) );

		$this->add_control( 'results_target', array(
			'label'       => esc_html__( 'Results Container ID', 'codelinden-elementor-form-addon' ),
			'description' => esc_html__( 'CSS ID of the element containing your posts loop (without #).', 'codelinden-elementor-form-addon' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
		) );

		$this->add_control( 'auto_apply', array(
			'label'        => esc_html__( 'Auto Apply (no button)', 'codelinden-elementor-form-addon' ),
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'label_on'     => esc_html__( 'Yes', 'codelinden-elementor-form-addon' ),
			'label_off'    => esc_html__( 'No', 'codelinden-elementor-form-addon' ),
		) );

		$this->add_control( 'sync_url', array(
			'label'    => esc_html__( 'Sync URL', 'codelinden-elementor-form-addon' ),
			'type'     => Controls_Manager::SWITCHER,
			'default'  => 'yes',
		) );

		$this->add_control( 'pagination_type', array(
			'label'   => esc_html__( 'Pagination', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'numbered',
			'options' => array(
				'numbered'  => esc_html__( 'Numbered', 'codelinden-elementor-form-addon' ),
				'load_more' => esc_html__( 'Load More', 'codelinden-elementor-form-addon' ),
				'infinite'  => esc_html__( 'Infinite Scroll', 'codelinden-elementor-form-addon' ),
				'none'      => esc_html__( 'None', 'codelinden-elementor-form-addon' ),
			),
		) );

		$this->add_control( 'no_results_text', array(
			'label'   => esc_html__( 'No Results Text', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'No results found.', 'codelinden-elementor-form-addon' ),
		) );

		$this->end_controls_section();

		/* ---- Filter Sections ---- */
		$this->start_controls_section( 'section_filters', array(
			'label' => esc_html__( 'Filter Sections', 'codelinden-elementor-form-addon' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		) );

		$repeater = new Repeater();

		$repeater->add_control( 'section_id', array(
			'label'       => esc_html__( 'Section ID', 'codelinden-elementor-form-addon' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => 'color',
		) );

		$repeater->add_control( 'section_label', array(
			'label'   => esc_html__( 'Label', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Filter', 'codelinden-elementor-form-addon' ),
		) );

		$repeater->add_control( 'filter_type', array(
			'label'   => esc_html__( 'Filter Type', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'checkbox',
			'options' => array(
				'checkbox'   => esc_html__( 'Checkbox', 'codelinden-elementor-form-addon' ),
				'radio'      => esc_html__( 'Radio', 'codelinden-elementor-form-addon' ),
				'select'     => esc_html__( 'Select', 'codelinden-elementor-form-addon' ),
				'range_dual' => esc_html__( 'Range (min/max)', 'codelinden-elementor-form-addon' ),
				'range'      => esc_html__( 'Range (single)', 'codelinden-elementor-form-addon' ),
				'date'       => esc_html__( 'Date Range', 'codelinden-elementor-form-addon' ),
				'search'     => esc_html__( 'Search', 'codelinden-elementor-form-addon' ),
			),
		) );

		$repeater->add_control( 'source_type', array(
			'label'   => esc_html__( 'Source', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'taxonomy',
			'options' => array(
				'taxonomy'  => esc_html__( 'Taxonomy', 'codelinden-elementor-form-addon' ),
				'post_meta' => esc_html__( 'Post Meta', 'codelinden-elementor-form-addon' ),
				'price'     => esc_html__( 'Price (WooCommerce)', 'codelinden-elementor-form-addon' ),
				'date'      => esc_html__( 'Post Date', 'codelinden-elementor-form-addon' ),
				'search'    => esc_html__( 'Full-Text Search', 'codelinden-elementor-form-addon' ),
				'author'    => esc_html__( 'Author', 'codelinden-elementor-form-addon' ),
			),
		) );

		$repeater->add_control( 'source_key', array(
			'label'       => esc_html__( 'Taxonomy / Meta Key', 'codelinden-elementor-form-addon' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => 'category',
		) );

		$repeater->add_control( 'options_source', array(
			'label'   => esc_html__( 'Options Source', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'auto',
			'options' => array(
				'auto'   => esc_html__( 'Auto (from taxonomy)', 'codelinden-elementor-form-addon' ),
				'manual' => esc_html__( 'Manual', 'codelinden-elementor-form-addon' ),
			),
			'condition' => array( 'filter_type' => array( 'checkbox', 'radio', 'select' ) ),
		) );

		$repeater->add_control( 'manual_options', array(
			'label'       => esc_html__( 'Manual Options (one per line, value|Label)', 'codelinden-elementor-form-addon' ),
			'type'        => Controls_Manager::TEXTAREA,
			'placeholder' => "red|Red\nblue|Blue",
			'condition'   => array( 'options_source' => 'manual', 'filter_type' => array( 'checkbox', 'radio', 'select' ) ),
		) );

		$repeater->add_control( 'range_min', array(
			'label'     => esc_html__( 'Min Value', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 0,
			'condition' => array( 'filter_type' => array( 'range', 'range_dual' ) ),
		) );

		$repeater->add_control( 'range_max', array(
			'label'     => esc_html__( 'Max Value', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 1000,
			'condition' => array( 'filter_type' => array( 'range', 'range_dual' ) ),
		) );

		$repeater->add_control( 'range_step', array(
			'label'     => esc_html__( 'Step', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 1,
			'condition' => array( 'filter_type' => array( 'range', 'range_dual' ) ),
		) );

		$repeater->add_control( 'range_prefix', array(
			'label'     => esc_html__( 'Prefix (e.g. $)', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::TEXT,
			'condition' => array( 'filter_type' => array( 'range', 'range_dual' ) ),
		) );

		$repeater->add_control( 'range_suffix', array(
			'label'     => esc_html__( 'Suffix (e.g. km)', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::TEXT,
			'condition' => array( 'filter_type' => array( 'range', 'range_dual' ) ),
		) );

		$repeater->add_control( 'collapsible', array(
			'label'    => esc_html__( 'Collapsible Section', 'codelinden-elementor-form-addon' ),
			'type'     => Controls_Manager::SWITCHER,
			'default'  => '',
		) );

		$repeater->add_control( 'default_collapsed', array(
			'label'     => esc_html__( 'Start Collapsed', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::SWITCHER,
			'default'   => '',
			'condition' => array( 'collapsible' => 'yes' ),
		) );

		$this->add_control( 'filter_sections', array(
			'label'       => esc_html__( 'Sections', 'codelinden-elementor-form-addon' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'default'     => array(),
			'title_field' => '{{{ section_label }}}',
		) );

		$this->end_controls_section();

		/* ---- Labels / Buttons ---- */
		$this->start_controls_section( 'section_labels', array(
			'label' => esc_html__( 'Labels', 'codelinden-elementor-form-addon' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'apply_text', array(
			'label'   => esc_html__( 'Apply Filters Button', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Apply Filters', 'codelinden-elementor-form-addon' ),
		) );

		$this->add_control( 'reset_text', array(
			'label'   => esc_html__( 'Reset Button', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Reset', 'codelinden-elementor-form-addon' ),
		) );

		$this->add_control( 'active_chips_label', array(
			'label'   => esc_html__( 'Active Filters Label', 'codelinden-elementor-form-addon' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Active:', 'codelinden-elementor-form-addon' ),
		) );

		$this->end_controls_section();
	}

	private function register_style_controls() {

		/* ---- Widget Wrapper ---- */
		$this->start_controls_section( 'section_wrapper_style', array(
			'label' => esc_html__( 'Widget Wrapper', 'codelinden-elementor-form-addon' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( 'wrapper_padding', array(
			'label'      => esc_html__( 'Padding', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .clefa-filter-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'     => 'wrapper_border',
			'selector' => '{{WRAPPER}} .clefa-filter-widget',
		) );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'wrapper_shadow',
			'selector' => '{{WRAPPER}} .clefa-filter-widget',
		) );

		$this->end_controls_section();

		/* ---- Section Heading ---- */
		$this->start_controls_section( 'section_heading_style', array(
			'label' => esc_html__( 'Section Heading', 'codelinden-elementor-form-addon' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'section_heading_color', array(
			'label'     => esc_html__( 'Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-filter-section-heading' => 'color: {{VALUE}};' ),
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'section_heading_typography',
			'selector' => '{{WRAPPER}} .clefa-filter-section-heading',
		) );

		$this->add_responsive_control( 'section_spacing', array(
			'label'      => esc_html__( 'Gap Between Sections', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .clefa-filter-section + .clefa-filter-section' => 'margin-top: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		/* ---- Items ---- */
		$this->start_controls_section( 'section_items_style', array(
			'label' => esc_html__( 'Filter Items', 'codelinden-elementor-form-addon' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'item_color', array(
			'label'     => esc_html__( 'Text Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-filter-option-label' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'item_active_color', array(
			'label'     => esc_html__( 'Active Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .clefa-filter-option input:checked + .clefa-filter-option-label' => 'color: {{VALUE}};',
			),
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'item_typography',
			'selector' => '{{WRAPPER}} .clefa-filter-option-label',
		) );

		$this->end_controls_section();

		/* ---- Chips ---- */
		$this->start_controls_section( 'section_chips_style', array(
			'label' => esc_html__( 'Active Filter Chips', 'codelinden-elementor-form-addon' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'chip_bg', array(
			'label'     => esc_html__( 'Background', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-filter-chip' => 'background: {{VALUE}};' ),
		) );

		$this->add_control( 'chip_color', array(
			'label'     => esc_html__( 'Text Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-filter-chip' => 'color: {{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------ */
	/* Render                                                                */
	/* ------------------------------------------------------------------ */

	protected function render() {
		$settings = $this->get_settings_for_display();

		// Enqueue filter assets
		wp_enqueue_script( 'clefa-filter-engine' );
		wp_enqueue_style( 'clefa-filter-engine' );

		$widget_id  = $this->get_id();
		$sections   = $settings['filter_sections'] ?? array();
		$auto_apply = $settings['auto_apply'] === 'yes';
		$sync_url   = $settings['sync_url']   === 'yes';

		$js_config = wp_json_encode( array(
			'widgetId'       => $widget_id,
			'restUrl'        => esc_url_raw( rest_url( 'clefa/v1/filter' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'autoApply'      => $auto_apply,
			'syncUrl'        => $sync_url,
			'resultsTarget'  => sanitize_key( $settings['results_target'] ?? '' ),
			'paginationType' => $settings['pagination_type'] ?? 'numbered',
			'postsPerPage'   => absint( $settings['posts_per_page'] ?? 9 ),
			'postType'       => sanitize_key( $settings['filter_post_type'] ?? 'post' ),
			'sections'       => array_map( function( $s ) {
				return array(
					'section_id'  => sanitize_key( $s['section_id'] ?? '' ),
					'filter_type' => $s['filter_type'] ?? 'checkbox',
					'source_type' => $s['source_type'] ?? 'taxonomy',
					'source_key'  => sanitize_key( $s['source_key'] ?? '' ),
				);
			}, $sections ),
		) );

		$tpl = CLEFA_Form_Renderer::locate_template( 'filter/filter-widget.php' );
		if ( $tpl ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( array(
				'settings'  => $settings,
				'sections'  => $sections,
				'widget_id' => $widget_id,
				'js_config' => $js_config,
			), EXTR_SKIP );
			include $tpl;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Get registered public post types for the dropdown.
	 */
	private function get_post_types() {
		$types  = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $types as $slug => $obj ) {
			if ( $slug === 'attachment' ) { continue; }
			$result[ $slug ] = $obj->labels->singular_name ?: $slug;
		}
		return $result;
	}

	/**
	 * Get options for a filter section (auto from taxonomy or manual).
	 */
	public static function get_section_options( array $section ) {
		$options_source = $section['options_source'] ?? 'auto';
		$filter_type    = $section['filter_type']    ?? 'checkbox';

		if ( 'manual' === $options_source ) {
			$lines  = explode( "\n", $section['manual_options'] ?? '' );
			$result = array();
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( ! $line ) { continue; }
				if ( strpos( $line, '|' ) !== false ) {
					list( $val, $label ) = explode( '|', $line, 2 );
					$result[] = array( 'value' => trim( $val ), 'label' => trim( $label ), 'count' => null );
				} else {
					$result[] = array( 'value' => $line, 'label' => $line, 'count' => null );
				}
			}
			return $result;
		}

		// Auto: from taxonomy
		if ( 'taxonomy' === ( $section['source_type'] ?? 'taxonomy' ) ) {
			$taxonomy = sanitize_key( $section['source_key'] ?? '' );
			$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
			if ( is_wp_error( $terms ) ) { return array(); }
			$result = array();
			foreach ( $terms as $term ) {
				$result[] = array( 'value' => $term->slug, 'label' => $term->name, 'count' => $term->count );
			}
			return $result;
		}

		return array();
	}
}
