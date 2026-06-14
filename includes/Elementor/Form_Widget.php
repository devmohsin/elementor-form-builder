<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
	return;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

class CLEFA_Form_Widget extends Widget_Base {

	public function get_name() {
		return 'clefa-form';
	}

	public function get_title() {
		return esc_html__( 'Form Addon', 'codelinden-elementor-form-addon' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'form', 'login', 'register', 'contact', 'codelinden', 'clefa' );
	}

	public function get_script_depends() {
		return array( 'clefa-event-dispatcher', 'clefa-condition-engine', 'clefa-validation-engine', 'clefa-form-engine' );
	}

	public function get_style_depends() {
		return array( 'clefa-form-engine' );
	}

	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	private function register_content_controls() {
		$this->start_controls_section(
			'section_form',
			array(
				'label' => esc_html__( 'Form', 'codelinden-elementor-form-addon' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$forms        = CLEFA_Tables::get_forms( array( 'status' => 'published', 'per_page' => 200 ) );
		$form_options = array( '' => __( '— Select a Form —', 'codelinden-elementor-form-addon' ) );
		foreach ( $forms as $form ) {
			$form_options[ $form['id'] ] = esc_html( $form['form_name'] );
		}

		$this->add_control(
			'form_id',
			array(
				'label'   => esc_html__( 'Select Form', 'codelinden-elementor-form-addon' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $form_options,
				'default' => '',
			)
		);

		$this->add_control(
			'display_mode',
			array(
				'label'   => esc_html__( 'Display Mode', 'codelinden-elementor-form-addon' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'normal'  => esc_html__( 'Normal', 'codelinden-elementor-form-addon' ),
					'compact' => esc_html__( 'Compact', 'codelinden-elementor-form-addon' ),
				),
				'default' => 'normal',
			)
		);

		$this->add_control(
			'show_form_title',
			array(
				'label'        => esc_html__( 'Show Form Title', 'codelinden-elementor-form-addon' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'codelinden-elementor-form-addon' ),
				'label_off'    => esc_html__( 'No', 'codelinden-elementor-form-addon' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'manage_link',
			array(
				'label'     => esc_html__( 'Manage in Admin', 'codelinden-elementor-form-addon' ),
				'type'      => Controls_Manager::RAW_HTML,
				'raw'       => sprintf(
					'<a href="%s" target="_blank" class="elementor-button elementor-button-default elementor-size-sm" style="margin-top:5px;">%s</a>',
					esc_url( admin_url( 'admin.php?page=clefa-forms' ) ),
					esc_html__( 'Open Form Builder', 'codelinden-elementor-form-addon' )
				),
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
	}

	private function register_style_controls() {
		// --- Form Wrapper ---
		$this->start_controls_section(
			'section_form_wrapper_style',
			array( 'label' => esc_html__( 'Form Wrapper', 'codelinden-elementor-form-addon' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control( 'form_max_width', array(
			'label'      => esc_html__( 'Max Width', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%', 'vw' ),
			'range'      => array( 'px' => array( 'min' => 200, 'max' => 1400 ) ),
			'selectors'  => array( '{{WRAPPER}} .clefa-form-wrap' => 'max-width: {{SIZE}}{{UNIT}}; margin-left: auto; margin-right: auto;' ),
		) );

		$this->add_responsive_control( 'form_padding', array(
			'label'      => esc_html__( 'Padding', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .clefa-form-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_group_control( Group_Control_Background::get_type(), array(
			'name'     => 'form_background',
			'selector' => '{{WRAPPER}} .clefa-form-wrap',
		) );

		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'     => 'form_border',
			'selector' => '{{WRAPPER}} .clefa-form-wrap',
		) );

		$this->add_responsive_control( 'form_border_radius', array(
			'label'      => esc_html__( 'Border Radius', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .clefa-form-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'form_shadow',
			'selector' => '{{WRAPPER}} .clefa-form-wrap',
		) );

		$this->end_controls_section();

		// --- Field Spacing ---
		$this->start_controls_section(
			'section_field_spacing',
			array( 'label' => esc_html__( 'Field Spacing', 'codelinden-elementor-form-addon' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_responsive_control( 'field_gap', array(
			'label'      => esc_html__( 'Gap Between Fields', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .clefa-fields-wrap' => 'display: flex; flex-direction: column; gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'label_gap', array(
			'label'      => esc_html__( 'Label-to-Input Gap', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
			'selectors'  => array( '{{WRAPPER}} .clefa-field-label' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		// --- Labels ---
		$this->start_controls_section(
			'section_label_style',
			array( 'label' => esc_html__( 'Labels', 'codelinden-elementor-form-addon' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'label_typography',
			'selector' => '{{WRAPPER}} .clefa-field-label',
		) );

		$this->add_control( 'label_color', array(
			'label'     => esc_html__( 'Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-field-label' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'required_color', array(
			'label'     => esc_html__( 'Required Asterisk Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-required' => 'color: {{VALUE}};' ),
		) );

		$this->end_controls_section();

		// --- Input Fields ---
		$this->start_controls_section(
			'section_input_style',
			array( 'label' => esc_html__( 'Input Fields', 'codelinden-elementor-form-addon' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->start_controls_tabs( 'tabs_input' );

		$this->start_controls_tab( 'tab_input_normal', array( 'label' => esc_html__( 'Normal', 'codelinden-elementor-form-addon' ) ) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'input_typography',
			'selector' => '{{WRAPPER}} .clefa-input, {{WRAPPER}} .clefa-textarea, {{WRAPPER}} .clefa-select',
		) );

		$this->add_control( 'input_text_color', array(
			'label'     => esc_html__( 'Text Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-input, {{WRAPPER}} .clefa-textarea, {{WRAPPER}} .clefa-select' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'input_bg_color', array(
			'label'     => esc_html__( 'Background Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-input, {{WRAPPER}} .clefa-textarea, {{WRAPPER}} .clefa-select' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_control( 'input_border_color', array(
			'label'     => esc_html__( 'Border Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-input, {{WRAPPER}} .clefa-textarea, {{WRAPPER}} .clefa-select' => 'border-color: {{VALUE}};' ),
		) );

		$this->end_controls_tab();

		$this->start_controls_tab( 'tab_input_focus', array( 'label' => esc_html__( 'Focus', 'codelinden-elementor-form-addon' ) ) );

		$this->add_control( 'input_focus_border_color', array(
			'label'     => esc_html__( 'Border Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .clefa-input:focus, {{WRAPPER}} .clefa-textarea:focus, {{WRAPPER}} .clefa-select:focus' => 'border-color: {{VALUE}};',
			),
		) );

		$this->add_control( 'input_focus_bg_color', array(
			'label'     => esc_html__( 'Background', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .clefa-input:focus, {{WRAPPER}} .clefa-textarea:focus, {{WRAPPER}} .clefa-select:focus' => 'background-color: {{VALUE}};',
			),
		) );

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_responsive_control( 'input_padding', array(
			'label'      => esc_html__( 'Padding', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'separator'  => 'before',
			'selectors'  => array( '{{WRAPPER}} .clefa-input, {{WRAPPER}} .clefa-textarea, {{WRAPPER}} .clefa-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'input_border_radius', array(
			'label'      => esc_html__( 'Border Radius', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .clefa-input, {{WRAPPER}} .clefa-textarea, {{WRAPPER}} .clefa-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'input_border_width', array(
			'label'      => esc_html__( 'Border Width', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 5 ) ),
			'selectors'  => array( '{{WRAPPER}} .clefa-input, {{WRAPPER}} .clefa-textarea, {{WRAPPER}} .clefa-select' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;' ),
		) );

		$this->end_controls_section();

		// --- Buttons ---
		$this->start_controls_section(
			'section_button_style',
			array( 'label' => esc_html__( 'Buttons', 'codelinden-elementor-form-addon' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'button_typography',
			'selector' => '{{WRAPPER}} .clefa-btn',
		) );

		$this->start_controls_tabs( 'tabs_button' );

		$this->start_controls_tab( 'tab_button_normal', array( 'label' => esc_html__( 'Normal', 'codelinden-elementor-form-addon' ) ) );
		$this->add_control( 'button_text_color', array(
			'label'     => esc_html__( 'Text Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-btn-primary' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'button_bg_color', array(
			'label'     => esc_html__( 'Background', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-btn-primary' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'tab_button_hover', array( 'label' => esc_html__( 'Hover', 'codelinden-elementor-form-addon' ) ) );
		$this->add_control( 'button_text_color_hover', array(
			'label'     => esc_html__( 'Text Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-btn-primary:hover' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'button_bg_color_hover', array(
			'label'     => esc_html__( 'Background', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-btn-primary:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_responsive_control( 'button_padding', array(
			'label'      => esc_html__( 'Padding', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'separator'  => 'before',
			'selectors'  => array( '{{WRAPPER}} .clefa-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'button_border_radius', array(
			'label'      => esc_html__( 'Border Radius', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .clefa-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'button_full_width', array(
			'label'        => esc_html__( 'Full Width Button', 'codelinden-elementor-form-addon' ),
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'codelinden-elementor-form-addon' ),
			'label_off'    => esc_html__( 'No', 'codelinden-elementor-form-addon' ),
			'return_value' => 'yes',
			'selectors'    => array( '{{WRAPPER}} .clefa-btn-submit' => 'width: 100%; justify-content: center;' ),
		) );

		$this->end_controls_section();

		// --- Progress Bar ---
		$this->start_controls_section(
			'section_progress_style',
			array( 'label' => esc_html__( 'Progress Bar', 'codelinden-elementor-form-addon' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_control( 'progress_bar_color', array(
			'label'     => esc_html__( 'Bar Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-progress-bar' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_control( 'progress_bg_color', array(
			'label'     => esc_html__( 'Track Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-progress-bar-wrap' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'progress_height', array(
			'label'      => esc_html__( 'Bar Height', 'codelinden-elementor-form-addon' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 2, 'max' => 20 ) ),
			'selectors'  => array(
				'{{WRAPPER}} .clefa-progress-bar-wrap' => 'height: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .clefa-progress-bar'      => 'height: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->end_controls_section();

		// --- Error State ---
		$this->start_controls_section(
			'section_error_style',
			array( 'label' => esc_html__( 'Error State', 'codelinden-elementor-form-addon' ), 'tab' => Controls_Manager::TAB_STYLE )
		);

		$this->add_control( 'error_text_color', array(
			'label'     => esc_html__( 'Error Message Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-field-error' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'error_input_border_color', array(
			'label'     => esc_html__( 'Error Input Border Color', 'codelinden-elementor-form-addon' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .clefa-input-error' => 'border-color: {{VALUE}};' ),
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'error_typography',
			'selector' => '{{WRAPPER}} .clefa-field-error',
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$settings    = $this->get_settings_for_display();
		$form_id     = absint( $settings['form_id'] ?? 0 );
		$is_edit     = \Elementor\Plugin::$instance->editor->is_edit_mode();
		$show_title  = 'yes' === ( $settings['show_form_title'] ?? '' );

		if ( ! $form_id ) {
			if ( $is_edit ) {
				echo '<div class="clefa-widget-placeholder" style="border:2px dashed #c8d0d8;padding:40px;text-align:center;border-radius:8px">';
				echo '<span class="eicon-form-horizontal" style="font-size:3rem;display:block;margin-bottom:12px;color:#93a5b7;"></span>';
				echo '<p style="margin:0;color:#69727d">' . esc_html__( 'Select a form from the panel to display it here.', 'codelinden-elementor-form-addon' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		$form = CLEFA_Tables::get_form( $form_id );
		if ( ! $form ) {
			echo '<div class="clefa-widget-placeholder"><p>' . esc_html__( 'Form not found.', 'codelinden-elementor-form-addon' ) . '</p></div>';
			return;
		}

		if ( $show_title ) {
			echo '<h3 class="clefa-form-widget-title">' . esc_html( $form['form_name'] ) . '</h3>';
		}

		echo CLEFA_Form_Renderer::render( $form_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
