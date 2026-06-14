<?php
/**
 * Filter widget main template.
 *
 * Variables: $settings, $sections, $widget_id, $js_config
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$auto_apply    = ( $settings['auto_apply'] ?? 'yes' ) === 'yes';
$reset_text    = esc_html( $settings['reset_text']  ?? __( 'Reset', 'codelinden-elementor-form-addon' ) );
$apply_text    = esc_html( $settings['apply_text']  ?? __( 'Apply Filters', 'codelinden-elementor-form-addon' ) );
$chips_label   = esc_html( $settings['active_chips_label'] ?? __( 'Active:', 'codelinden-elementor-form-addon' ) );
$mobile_text   = esc_html( $settings['mobile_toggle_text'] ?? __( 'Filters', 'codelinden-elementor-form-addon' ) );
$mobile_close  = esc_html( $settings['mobile_close_text']  ?? __( 'Close', 'codelinden-elementor-form-addon' ) );
$mobile_mode   = ( $settings['mobile_drawer'] ?? 'yes' ) === 'yes';
?>
<div
	class="clefa-filter-widget<?php echo $mobile_mode ? ' clefa-filter-has-drawer' : ''; ?>"
	data-clefa-filter-widget="<?php echo esc_attr( $widget_id ); ?>"
	data-clefa-filter-config="<?php echo esc_attr( $js_config ); ?>"
>
	<?php if ( $mobile_mode ) : ?>
	<?php /* Mobile drawer toggle — visible only on small screens */ ?>
	<button type="button" class="clefa-filter-mobile-toggle" data-clefa-filter-mobile-open aria-expanded="false" aria-controls="clefa-filter-drawer-<?php echo esc_attr( $widget_id ); ?>">
		<span class="clefa-filter-mobile-toggle-icon" aria-hidden="true"></span>
		<?php echo $mobile_text; ?>
	</button>

	<?php /* Backdrop — click to close drawer */ ?>
	<div class="clefa-filter-backdrop" data-clefa-filter-backdrop aria-hidden="true"></div>
	<?php endif; ?>
	<?php /* Active filter chips */ ?>
	<div class="clefa-filter-chips-bar" data-clefa-chips-bar style="display:none">
		<span class="clefa-filter-chips-label"><?php echo $chips_label; ?></span>
		<div class="clefa-filter-chips" data-clefa-chips></div>
		<button type="button" class="clefa-filter-reset-all" data-clefa-filter-reset>
			<?php echo $reset_text; ?>
		</button>
	</div>

	<?php /* Filter form */ ?>
	<form class="clefa-filter-form" id="clefa-filter-drawer-<?php echo esc_attr( $widget_id ); ?>" data-clefa-filter-form novalidate>
		<?php if ( $mobile_mode ) : ?>
		<div class="clefa-filter-drawer-header">
			<span class="clefa-filter-drawer-title"><?php echo $mobile_text; ?></span>
			<button type="button" class="clefa-filter-drawer-close" data-clefa-filter-mobile-close aria-label="<?php echo $mobile_close; ?>">
				<span aria-hidden="true">&#x2715;</span>
			</button>
		</div>
		<?php endif; ?>
		<?php
		foreach ( $sections as $section ) :
			$sec_id   = sanitize_key( $section['section_id'] ?? '' );
			$ftype    = $section['filter_type'] ?? 'checkbox';
			$sec_tpl  = CLEFA_Form_Renderer::locate_template( 'filter/sections/' . sanitize_key( $ftype ) . '.php' );
			if ( ! $sec_id || ! $sec_tpl ) { continue; }

			$collapsible = ( $section['collapsible'] ?? '' ) === 'yes';
			$collapsed   = $collapsible && ( $section['default_collapsed'] ?? '' ) === 'yes';
			$options     = CLEFA_Filter_Widget::get_section_options( $section );
		?>
		<div
			class="clefa-filter-section<?php echo $collapsible ? ' clefa-filter-section-collapsible' : ''; ?>"
			data-clefa-filter-section="<?php echo esc_attr( $sec_id ); ?>"
			data-clefa-filter-type="<?php echo esc_attr( $ftype ); ?>"
			<?php if ( $collapsed ) : ?>data-clefa-collapsed<?php endif; ?>
		>
			<?php if ( ! empty( $section['section_label'] ) ) : ?>
			<button
				type="button"
				class="clefa-filter-section-heading<?php echo $collapsible ? ' clefa-filter-section-toggle' : ''; ?>"
				<?php if ( $collapsible ) : ?>
					aria-expanded="<?php echo $collapsed ? 'false' : 'true'; ?>"
					aria-controls="clefa-fs-<?php echo esc_attr( $sec_id ); ?>"
				<?php endif; ?>
			>
				<?php echo esc_html( $section['section_label'] ); ?>
				<?php if ( $collapsible ) : ?>
				<span class="clefa-filter-section-chevron" aria-hidden="true"></span>
				<?php endif; ?>
			</button>
			<?php endif; ?>

			<div
				class="clefa-filter-section-body"
				id="clefa-fs-<?php echo esc_attr( $sec_id ); ?>"
				<?php if ( $collapsed ) : ?>style="display:none"<?php endif; ?>
			>
				<?php
				// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
				extract( array(
					'section' => $section,
					'sec_id'  => $sec_id,
					'options' => $options,
				), EXTR_OVERWRITE );
				include $sec_tpl;
				?>
			</div>
		</div>
		<?php endforeach; ?>

		<?php if ( ! $auto_apply ) : ?>
		<div class="clefa-filter-actions">
			<button type="button" class="clefa-btn clefa-btn-primary" data-clefa-filter-apply>
				<?php echo $apply_text; ?>
			</button>
			<button type="button" class="clefa-btn clefa-btn-secondary" data-clefa-filter-reset>
				<?php echo $reset_text; ?>
			</button>
		</div>
		<?php endif; ?>
	</form>

	<?php /* Loading indicator */ ?>
	<div class="clefa-filter-loading" data-clefa-filter-loading aria-hidden="true" style="display:none">
		<span class="clefa-filter-spinner"></span>
	</div>
</div>
