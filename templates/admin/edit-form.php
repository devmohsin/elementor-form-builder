<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$form_id   = isset( $form_id ) ? absint( $form_id ) : 0;
$form_name = isset( $form_data['form_name'] ) ? esc_attr( $form_data['form_name'] ) : esc_attr__( 'Untitled Form', 'codelinden-elementor-form-addon' );
$is_new       = ! $form_id;
$is_published = isset( $form_data['status'] ) && 'published' === $form_data['status'];
?>
<div class="clefa-builder-wrap" id="clefa-builder" data-clefa-form-id="<?php echo esc_attr( $form_id ); ?>">

	<?php CLEFA_Plugin_Dependencies::render_builder_notices(); ?>

	<?php if ( ! empty( $form_missing ) ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'That form no longer exists. You are editing a new form — save to create it.', 'codelinden-elementor-form-addon' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Top Bar -->
	<div class="clefa-topbar" data-clefa-role="topbar">
		<div class="clefa-topbar-left">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=clefa-forms' ) ); ?>" class="clefa-topbar-back" title="<?php esc_attr_e( 'Back to All Forms', 'codelinden-elementor-form-addon' ); ?>">
				<span class="dashicons dashicons-arrow-left-alt"></span>
			</a>
			<div class="clefa-topbar-form-name-wrap">
				<input type="text"
				       id="clefa-form-name"
				       class="clefa-topbar-form-name"
				       value="<?php echo $form_name; ?>"
				       placeholder="<?php esc_attr_e( 'Form name...', 'codelinden-elementor-form-addon' ); ?>"
				       data-clefa-state="form_name" />
				<span class="clefa-unsaved-indicator" data-clefa-role="unsaved-indicator" title="<?php esc_attr_e( 'Unsaved changes', 'codelinden-elementor-form-addon' ); ?>"></span>
			</div>
		</div>

		<div class="clefa-topbar-center">
			<nav class="clefa-tab-nav" data-clefa-role="tab-nav">
				<button type="button" class="clefa-tab-btn clefa-tab-active" data-clefa-tab="builder">
					<span class="dashicons dashicons-layout"></span>
					<?php esc_html_e( 'Builder', 'codelinden-elementor-form-addon' ); ?>
				</button>
				<button type="button" class="clefa-tab-btn" data-clefa-tab="settings">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Settings', 'codelinden-elementor-form-addon' ); ?>
				</button>
				<button type="button" class="clefa-tab-btn" data-clefa-tab="actions">
					<span class="dashicons dashicons-controls-play"></span>
					<?php esc_html_e( 'Actions', 'codelinden-elementor-form-addon' ); ?>
				</button>
				<button type="button" class="clefa-tab-btn" data-clefa-tab="notifications">
					<span class="dashicons dashicons-email-alt"></span>
					<?php esc_html_e( 'Notifications', 'codelinden-elementor-form-addon' ); ?>
				</button>
				<button type="button" class="clefa-tab-btn" data-clefa-tab="submissions">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Submissions', 'codelinden-elementor-form-addon' ); ?>
				</button>
			</nav>
		</div>

		<div class="clefa-topbar-right">
			<button type="button" class="clefa-btn clefa-btn-sm clefa-btn-ghost" data-clefa-action="simulate-form">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Simulate', 'codelinden-elementor-form-addon' ); ?>
			</button>
			<button type="button"
			        class="clefa-btn clefa-btn-sm <?php echo $is_published ? 'clefa-btn-warning' : 'clefa-btn-outline'; ?>"
			        data-clefa-action="publish-form"
			        data-clefa-published="<?php echo $is_published ? '1' : '0'; ?>">
				<span class="dashicons <?php echo $is_published ? 'dashicons-hidden' : 'dashicons-visibility'; ?>"></span>
				<?php echo $is_published ? esc_html__( 'Unpublish', 'codelinden-elementor-form-addon' ) : esc_html__( 'Publish', 'codelinden-elementor-form-addon' ); ?>
			</button>
			<button type="button" class="clefa-btn clefa-btn-sm clefa-btn-primary" data-clefa-action="save-form">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'Save', 'codelinden-elementor-form-addon' ); ?>
			</button>
		</div>
	</div>

	<!-- Main Builder Area -->
	<div class="clefa-builder-body" data-clefa-role="builder-body">

		<!-- Left Sidebar: Field Types -->
		<aside class="clefa-sidebar-fields" data-clefa-role="fields-sidebar">
			<div class="clefa-sidebar-header">
				<input type="text"
				       class="clefa-sidebar-search"
				       placeholder="<?php esc_attr_e( 'Search fields...', 'codelinden-elementor-form-addon' ); ?>"
				       data-clefa-action="search-fields" />
			</div>
			<div class="clefa-sidebar-field-groups" data-clefa-role="field-groups">
				<!-- Populated by JS from clefaBuilderData.fieldTypes -->
			</div>
		</aside>

		<!-- Canvas -->
		<main class="clefa-builder-canvas" data-clefa-role="canvas">

			<!-- Builder Tab -->
			<div class="clefa-tab-panel clefa-tab-panel-active" data-clefa-panel="builder">
				<div class="clefa-steps-wrap" data-clefa-role="steps-wrap">
					<!-- Steps rendered by JS -->
				</div>
				<button type="button" class="clefa-add-step-btn" data-clefa-action="add-step">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Step', 'codelinden-elementor-form-addon' ); ?>
				</button>
			</div>

			<!-- Settings Tab -->
			<div class="clefa-tab-panel" data-clefa-panel="settings">
				<div class="clefa-settings-form" data-clefa-role="settings-form">
					<div class="clefa-settings-section">
						<h3 class="clefa-settings-section-title"><?php esc_html_e( 'Form Identity', 'codelinden-elementor-form-addon' ); ?></h3>
						<div class="clefa-field-row">
							<label><?php esc_html_e( 'Form Type', 'codelinden-elementor-form-addon' ); ?></label>
							<select data-clefa-setting="form_type">
								<option value="standard"><?php esc_html_e( 'Standard Form', 'codelinden-elementor-form-addon' ); ?></option>
								<option value="login"><?php esc_html_e( 'Login Form', 'codelinden-elementor-form-addon' ); ?></option>
								<option value="register"><?php esc_html_e( 'Registration Form', 'codelinden-elementor-form-addon' ); ?></option>
								<option value="registration"><?php esc_html_e( 'Registration Form (alt)', 'codelinden-elementor-form-addon' ); ?></option>
								<option value="onboarding"><?php esc_html_e( 'Onboarding Flow', 'codelinden-elementor-form-addon' ); ?></option>
								<option value="upload"><?php esc_html_e( 'Upload Form', 'codelinden-elementor-form-addon' ); ?></option>
								<option value="filter"><?php esc_html_e( 'Sidebar Filter', 'codelinden-elementor-form-addon' ); ?></option>
							</select>
						</div>
						<div class="clefa-field-row">
							<label><?php esc_html_e( 'Description (internal)', 'codelinden-elementor-form-addon' ); ?></label>
							<textarea rows="2" data-clefa-setting="description" placeholder="<?php esc_attr_e( 'Internal notes...', 'codelinden-elementor-form-addon' ); ?>"></textarea>
						</div>
					</div>

					<!-- ========= THEME PICKER ========= -->
					<?php
					/**
					 * Each theme entry: key => [ label, bg, input_bg, border, accent, text, btn_text, description ]
					 */
					$clefa_themes = array(
						'' => array(
							'label'   => 'Default',
							'desc'    => 'Clean white, indigo accent',
							'bg'      => '#ffffff',
							'ibg'     => '#ffffff',
							'border'  => '#e2e8f0',
							'accent'  => '#4f46e5',
							'text'    => '#1e293b',
							'btn'     => '#4f46e5',
							'btntxt'  => '#ffffff',
						),
						'minimal' => array(
							'label'   => 'Minimal',
							'desc'    => 'Bottom-border only inputs',
							'bg'      => '#ffffff',
							'ibg'     => 'transparent',
							'border'  => '#e2e8f0',
							'accent'  => '#4f46e5',
							'text'    => '#1e293b',
							'btn'     => '#4f46e5',
							'btntxt'  => '#ffffff',
						),
						'card' => array(
							'label'   => 'Card',
							'desc'    => 'Each field in a lifted box',
							'bg'      => '#f8fafc',
							'ibg'     => '#f8fafc',
							'border'  => '#e2e8f0',
							'accent'  => '#4f46e5',
							'text'    => '#1e293b',
							'btn'     => '#4f46e5',
							'btntxt'  => '#ffffff',
						),
						'rounded' => array(
							'label'   => 'Rounded',
							'desc'    => 'Soft radii, pill buttons',
							'bg'      => '#ffffff',
							'ibg'     => '#ffffff',
							'border'  => '#e2e8f0',
							'accent'  => '#4f46e5',
							'text'    => '#1e293b',
							'btn'     => '#4f46e5',
							'btntxt'  => '#ffffff',
						),
						'dark' => array(
							'label'   => 'Dark',
							'desc'    => 'Dark navy background',
							'bg'      => '#0f172a',
							'ibg'     => '#1e293b',
							'border'  => '#334155',
							'accent'  => '#4f46e5',
							'text'    => '#f1f5f9',
							'btn'     => '#4f46e5',
							'btntxt'  => '#ffffff',
						),
						'glass' => array(
							'label'   => 'Glass',
							'desc'    => 'Frosted glassmorphism',
							'bg'      => 'rgba(255,255,255,0.18)',
							'ibg'     => 'rgba(255,255,255,0.22)',
							'border'  => 'rgba(255,255,255,0.35)',
							'accent'  => '#4f46e5',
							'text'    => '#1e293b',
							'btn'     => '#4f46e5',
							'btntxt'  => '#ffffff',
						),
						'flat' => array(
							'label'   => 'Flat',
							'desc'    => 'Material flat, no borders',
							'bg'      => '#f8fafc',
							'ibg'     => '#f1f5f9',
							'border'  => 'transparent',
							'accent'  => '#4f46e5',
							'text'    => '#1e293b',
							'btn'     => '#4f46e5',
							'btntxt'  => '#ffffff',
						),
						'soft' => array(
							'label'   => 'Soft',
							'desc'    => 'Pastel purple, ultra-gentle',
							'bg'      => '#fdf4ff',
							'ibg'     => '#faf5ff',
							'border'  => '#ddd6fe',
							'accent'  => '#7c3aed',
							'text'    => '#3b0764',
							'btn'     => '#7c3aed',
							'btntxt'  => '#ffffff',
						),
						'ocean' => array(
							'label'   => 'Ocean',
							'desc'    => 'Deep sea blue / sky',
							'bg'      => '#e0f2fe',
							'ibg'     => '#f0f9ff',
							'border'  => '#bae6fd',
							'accent'  => '#0ea5e9',
							'text'    => '#0c4a6e',
							'btn'     => '#0284c7',
							'btntxt'  => '#ffffff',
						),
						'forest' => array(
							'label'   => 'Forest',
							'desc'    => 'Earthy greens, natural',
							'bg'      => '#dcfce7',
							'ibg'     => '#f0fdf4',
							'border'  => '#bbf7d0',
							'accent'  => '#16a34a',
							'text'    => '#14532d',
							'btn'     => '#16a34a',
							'btntxt'  => '#ffffff',
						),
						'sunset' => array(
							'label'   => 'Sunset',
							'desc'    => 'Warm orange → rose',
							'bg'      => '#fff7ed',
							'ibg'     => '#fff7ed',
							'border'  => '#fed7aa',
							'accent'  => '#ea580c',
							'text'    => '#431407',
							'btn'     => '#ea580c',
							'btntxt'  => '#ffffff',
						),
						'neon' => array(
							'label'   => 'Neon',
							'desc'    => 'Dark base, electric lime',
							'bg'      => '#020617',
							'ibg'     => '#0f172a',
							'border'  => '#1e293b',
							'accent'  => '#a3e635',
							'text'    => '#f8fafc',
							'btn'     => '#a3e635',
							'btntxt'  => '#020617',
						),
					);

					/**
					 * Renders a 140×96 SVG mini-preview for a theme.
					 */
					function clefa_render_theme_svg( $t ) {
						$bg      = esc_attr( $t['bg'] );
						$ibg     = esc_attr( $t['ibg'] );
						$border  = esc_attr( $t['border'] );
						$accent  = esc_attr( $t['accent'] );
						$text    = esc_attr( $t['text'] );
						$btn     = esc_attr( $t['btn'] );
						$btntxt  = esc_attr( $t['btntxt'] );
						$radius  = ( strpos( $t['label'], 'Rounded' ) !== false ) ? '12' : '4';
						$ibr     = ( strpos( $t['label'], 'Minimal' ) !== false ) ? '0' : $radius;
						$bb_only = ( strpos( $t['label'], 'Minimal' ) !== false || strpos( $t['label'], 'Flat' ) !== false ) ? '1' : '0';

						$border_stroke = ( strpos( $t['border'], 'transparent' ) !== false || strpos( $t['border'], 'rgba' ) !== false )
							? '#cccccc'
							: $t['border'];

						ob_start(); ?>
<svg xmlns="http://www.w3.org/2000/svg" width="140" height="96" viewBox="0 0 140 96">
  <!-- Form background -->
  <rect width="140" height="96" fill="<?php echo $bg; ?>" rx="6"/>
  <!-- Label 1 -->
  <rect x="10" y="10" width="32" height="4" rx="2" fill="<?php echo $text; ?>" opacity=".45"/>
  <!-- Input 1 -->
  <?php if ( $bb_only === '1' ) : ?>
  <rect x="10" y="18" width="120" height="14" rx="0" fill="<?php echo $ibg; ?>"/>
  <line x1="10" y1="32" x2="130" y2="32" stroke="<?php echo $accent; ?>" stroke-width="2"/>
  <?php else : ?>
  <rect x="10" y="18" width="120" height="14" rx="<?php echo $ibr; ?>" fill="<?php echo $ibg; ?>" stroke="<?php echo esc_attr( $border_stroke ); ?>" stroke-width="1"/>
  <?php endif; ?>
  <!-- Label 2 -->
  <rect x="10" y="38" width="28" height="4" rx="2" fill="<?php echo $text; ?>" opacity=".45"/>
  <!-- Input 2 -->
  <?php if ( $bb_only === '1' ) : ?>
  <rect x="10" y="46" width="120" height="14" rx="0" fill="<?php echo $ibg; ?>"/>
  <line x1="10" y1="60" x2="130" y2="60" stroke="<?php echo esc_attr( $border_stroke ); ?>" stroke-width="1"/>
  <?php else : ?>
  <rect x="10" y="46" width="120" height="14" rx="<?php echo $ibr; ?>" fill="<?php echo $ibg; ?>" stroke="<?php echo esc_attr( $border_stroke ); ?>" stroke-width="1"/>
  <?php endif; ?>
  <!-- Button -->
  <rect x="10" y="70" width="60" height="16" rx="<?php echo ( strpos( $t['label'], 'Rounded' ) !== false ) ? '8' : '4'; ?>" fill="<?php echo $btn; ?>"/>
  <rect x="15" y="75" width="30" height="4" rx="2" fill="<?php echo $btntxt; ?>" opacity=".85"/>
  <!-- Accent dots (visual flair) -->
  <circle cx="128" cy="78" r="4" fill="<?php echo $accent; ?>" opacity=".35"/>
  <circle cx="120" cy="78" r="2.5" fill="<?php echo $accent; ?>" opacity=".2"/>
</svg>
<?php return ob_get_clean();
					}
					?>
					<div class="clefa-settings-section">
						<h3 class="clefa-settings-section-title"><?php esc_html_e( 'Form Theme', 'codelinden-elementor-form-addon' ); ?></h3>
						<p class="clefa-settings-section-desc"><?php esc_html_e( 'Choose a visual style. Fine-tune colours below.', 'codelinden-elementor-form-addon' ); ?></p>

						<div class="clefa-theme-picker" id="clefa-theme-picker">
							<?php foreach ( $clefa_themes as $key => $theme ) : ?>
							<button
								type="button"
								class="clefa-theme-pick-card"
								data-clefa-theme-key="<?php echo esc_attr( $key ); ?>">
								<span class="clefa-theme-pick-preview">
									<?php echo clefa_render_theme_svg( $theme ); ?>
								</span>
								<span class="clefa-theme-pick-label"><?php echo esc_html( $theme['label'] ); ?></span>
								<span class="clefa-theme-pick-desc"><?php echo esc_html( $theme['desc'] ); ?></span>
							</button>
							<?php endforeach; ?>
						</div>

						<!-- Hidden input keeps the current value for JS -->
						<input type="hidden" id="clefa-theme-value" data-clefa-setting="form_theme" value="">
					</div>

					<!-- ========= STYLE CUSTOMIZATION ========= -->
					<div class="clefa-settings-section">
						<h3 class="clefa-settings-section-title"><?php esc_html_e( 'Style Overrides', 'codelinden-elementor-form-addon' ); ?></h3>
						<p class="clefa-settings-section-desc"><?php esc_html_e( 'Override individual CSS variables — these layer on top of any theme.', 'codelinden-elementor-form-addon' ); ?></p>

						<div class="clefa-style-grid">
							<?php
							$style_fields = array(
								array( 'key' => 'primary_color',   'label' => 'Primary / Accent',    'type' => 'color',  'default' => '' ),
								array( 'key' => 'bg_color',        'label' => 'Form Background',      'type' => 'color',  'default' => '' ),
								array( 'key' => 'input_bg',        'label' => 'Input Background',     'type' => 'color',  'default' => '' ),
								array( 'key' => 'border_color',    'label' => 'Border Colour',        'type' => 'color',  'default' => '' ),
								array( 'key' => 'text_color',      'label' => 'Text Colour',          'type' => 'color',  'default' => '' ),
								array( 'key' => 'muted_color',     'label' => 'Placeholder / Muted',  'type' => 'color',  'default' => '' ),
								array( 'key' => 'label_color',     'label' => 'Label Colour',         'type' => 'color',  'default' => '' ),
								array( 'key' => 'error_color',     'label' => 'Error Colour',         'type' => 'color',  'default' => '' ),
								array( 'key' => 'radius',          'label' => 'Border Radius (px)',   'type' => 'range',  'min' => '0', 'max' => '24', 'step' => '1', 'default' => '' ),
								array( 'key' => 'label_weight',    'label' => 'Label Font Weight',    'type' => 'select',
									'options' => array( '' => 'Theme default', '400' => 'Normal', '500' => 'Medium', '600' => 'Semi-Bold', '700' => 'Bold' ) ),
								array( 'key' => 'label_size',      'label' => 'Label Font Size',      'type' => 'select',
									'options' => array( '' => 'Theme default', '0.75rem' => 'XS (12px)', '0.8125rem' => 'S (13px)', '0.875rem' => 'SM (14px)', '0.9375rem' => 'M (15px)', '1rem' => 'L (16px)' ) ),
								array( 'key' => 'input_padding',   'label' => 'Input Padding',        'type' => 'select',
									'options' => array( '' => 'Theme default', '0.25rem 0.5rem' => 'Tight', '0.5rem 0.75rem' => 'Normal', '0.65rem 1rem' => 'Comfortable', '0.85rem 1.25rem' => 'Spacious' ) ),
								array( 'key' => 'shadow',          'label' => 'Input Shadow',         'type' => 'select',
									'options' => array( '' => 'Theme default', 'none' => 'None', '0 1px 2px rgba(0,0,0,.06)' => 'Subtle', '0 1px 4px rgba(0,0,0,.1)' => 'Light', '0 2px 8px rgba(0,0,0,.15)' => 'Medium' ) ),
							);
							?>
							<?php foreach ( $style_fields as $sf ) : ?>
							<div class="clefa-style-field">
								<label class="clefa-style-field-label">
									<?php echo esc_html( $sf['label'] ); ?>
								</label>
								<?php if ( $sf['type'] === 'color' ) : ?>
								<div class="clefa-color-field">
									<input type="color"
									       class="clefa-color-picker"
									       data-clefa-custom-style="<?php echo esc_attr( $sf['key'] ); ?>"
									       value="<?php echo esc_attr( $sf['default'] ); ?>"
									       title="<?php echo esc_attr( $sf['label'] ); ?>">
									<input type="text"
									       class="clefa-color-text"
									       data-clefa-custom-style-text="<?php echo esc_attr( $sf['key'] ); ?>"
									       placeholder="<?php esc_attr_e( 'e.g. #4f46e5 or empty', 'codelinden-elementor-form-addon' ); ?>"
									       maxlength="25">
									<button type="button" class="clefa-color-clear" data-clefa-clear-style="<?php echo esc_attr( $sf['key'] ); ?>" title="<?php esc_attr_e( 'Clear', 'codelinden-elementor-form-addon' ); ?>">×</button>
								</div>
								<?php elseif ( $sf['type'] === 'range' ) : ?>
								<div class="clefa-range-field">
									<input type="range"
									       class="clefa-range-input"
									       data-clefa-custom-style="<?php echo esc_attr( $sf['key'] ); ?>"
									       min="<?php echo esc_attr( $sf['min'] ); ?>"
									       max="<?php echo esc_attr( $sf['max'] ); ?>"
									       step="<?php echo esc_attr( $sf['step'] ); ?>"
									       value="<?php echo esc_attr( $sf['default'] ); ?>">
									<span class="clefa-range-value" data-clefa-range-label="<?php echo esc_attr( $sf['key'] ); ?>">—</span>
								</div>
								<?php elseif ( $sf['type'] === 'select' ) : ?>
								<select class="clefa-style-select"
								        data-clefa-custom-style="<?php echo esc_attr( $sf['key'] ); ?>">
									<?php foreach ( $sf['options'] as $v => $ol ) : ?>
									<option value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( $ol ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php endif; ?>
							</div>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="clefa-settings-section">
						<h3 class="clefa-settings-section-title"><?php esc_html_e( 'Submission', 'codelinden-elementor-form-addon' ); ?></h3>
						<div class="clefa-field-row clefa-field-row-toggle">
							<label><?php esc_html_e( 'Store Submissions', 'codelinden-elementor-form-addon' ); ?></label>
							<div class="clefa-toggle" data-clefa-setting="store_submissions" data-clefa-value="true">
								<span class="clefa-toggle-track"></span>
							</div>
						</div>
						<div class="clefa-field-row clefa-field-row-toggle">
							<label><?php esc_html_e( 'Enable AJAX Submit', 'codelinden-elementor-form-addon' ); ?></label>
							<div class="clefa-toggle" data-clefa-setting="enable_ajax" data-clefa-value="true">
								<span class="clefa-toggle-track"></span>
							</div>
						</div>
						<div class="clefa-field-row">
							<label><?php esc_html_e( 'Success Message', 'codelinden-elementor-form-addon' ); ?></label>
							<input type="text" data-clefa-setting="success_message" placeholder="<?php esc_attr_e( 'Form submitted successfully.', 'codelinden-elementor-form-addon' ); ?>" />
						</div>
						<div class="clefa-field-row">
							<label><?php esc_html_e( 'Redirect URL (after submit)', 'codelinden-elementor-form-addon' ); ?></label>
							<input type="url" data-clefa-setting="redirect_url" placeholder="https://" />
						</div>
					</div>

					<div class="clefa-settings-section">
						<h3 class="clefa-settings-section-title"><?php esc_html_e( 'Access Control', 'codelinden-elementor-form-addon' ); ?></h3>
						<div class="clefa-field-row clefa-field-row-toggle">
							<label><?php esc_html_e( 'Require Login', 'codelinden-elementor-form-addon' ); ?></label>
							<div class="clefa-toggle" data-clefa-setting="require_login" data-clefa-value="false">
								<span class="clefa-toggle-track"></span>
							</div>
						</div>
					</div>

					<div class="clefa-settings-section">
						<h3 class="clefa-settings-section-title"><?php esc_html_e( 'Security', 'codelinden-elementor-form-addon' ); ?></h3>
						<div class="clefa-field-row clefa-field-row-toggle">
							<label><?php esc_html_e( 'Enable Anti-Spam (Honeypot)', 'codelinden-elementor-form-addon' ); ?></label>
							<div class="clefa-toggle" data-clefa-setting="enable_antispam" data-clefa-value="true">
								<span class="clefa-toggle-track"></span>
							</div>
						</div>
						<div class="clefa-field-row clefa-field-row-toggle">
							<label><?php esc_html_e( 'Dynamic Nonce Refresh', 'codelinden-elementor-form-addon' ); ?></label>
							<div class="clefa-toggle" data-clefa-setting="enable_nonce_refresh" data-clefa-value="true">
								<span class="clefa-toggle-track"></span>
							</div>
						</div>
					</div>

					<div class="clefa-settings-section">
						<h3 class="clefa-settings-section-title"><?php esc_html_e( 'Developer', 'codelinden-elementor-form-addon' ); ?></h3>
						<div class="clefa-field-row clefa-field-row-toggle">
							<label><?php esc_html_e( 'Dispatch JS Events', 'codelinden-elementor-form-addon' ); ?></label>
							<div class="clefa-toggle" data-clefa-setting="enable_events" data-clefa-value="true">
								<span class="clefa-toggle-track"></span>
							</div>
						</div>
						<div class="clefa-field-row clefa-field-row-toggle">
							<label><?php esc_html_e( 'Debug Console Notices', 'codelinden-elementor-form-addon' ); ?></label>
							<div class="clefa-toggle" data-clefa-setting="enable_debug_console" data-clefa-value="false">
								<span class="clefa-toggle-track"></span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Actions Tab -->
			<div class="clefa-tab-panel" data-clefa-panel="actions">
				<div class="clefa-actions-header">
					<p class="clefa-actions-intro"><?php esc_html_e( 'Actions run after successful form submission. Each action is scoped to a single task. Add, order and configure them below.', 'codelinden-elementor-form-addon' ); ?></p>
					<button type="button" class="clefa-btn clefa-btn-primary" data-clefa-action="open-action-picker">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Action', 'codelinden-elementor-form-addon' ); ?>
					</button>
				</div>
				<div class="clefa-actions-list" data-clefa-role="actions-list">
					<!-- Actions rendered by JS -->
				</div>
			</div>

			<!-- Notifications Tab -->
			<div class="clefa-tab-panel" data-clefa-panel="notifications">
				<div class="clefa-notifications-header">
					<p class="clefa-notifications-intro"><?php esc_html_e( 'Send email notifications when the form is submitted. Add multiple notifications for different recipients.', 'codelinden-elementor-form-addon' ); ?></p>
					<button type="button" class="clefa-btn clefa-btn-primary" data-clefa-action="add-notification">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Notification', 'codelinden-elementor-form-addon' ); ?>
					</button>
				</div>
				<div class="clefa-notifications-list" data-clefa-role="notifications-list">
					<!-- Notifications rendered by JS -->
				</div>
			</div>

			<!-- Submissions Tab -->
			<div class="clefa-tab-panel" data-clefa-panel="submissions">
				<div class="clefa-submissions-panel" data-clefa-role="submissions-panel">
					<?php if ( $form_id ) : ?>
					<div class="clefa-submissions-toolbar">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=clefa-submissions&form_id=' . $form_id ) ); ?>"
						   class="clefa-btn clefa-btn-secondary" target="_blank">
							<span class="dashicons dashicons-external"></span>
							<?php esc_html_e( 'View All Submissions', 'codelinden-elementor-form-addon' ); ?>
						</a>
					</div>
					<div class="clefa-submissions-inline" data-clefa-role="inline-submissions">
						<p class="clefa-text-muted"><?php esc_html_e( 'Loading submissions...', 'codelinden-elementor-form-addon' ); ?></p>
					</div>
					<?php else : ?>
					<div class="clefa-empty-state clefa-empty-state-sm">
						<p><?php esc_html_e( 'Save the form first to view submissions.', 'codelinden-elementor-form-addon' ); ?></p>
					</div>
					<?php endif; ?>
				</div>
			</div>

		</main>

		<!-- Right Panel: Item Settings -->
		<aside class="clefa-settings-panel" data-clefa-role="settings-panel">
			<div class="clefa-settings-panel-empty" data-clefa-role="panel-empty">
				<span class="dashicons dashicons-editor-help clefa-settings-panel-icon"></span>
				<p><?php esc_html_e( 'Select a field or step to edit its settings.', 'codelinden-elementor-form-addon' ); ?></p>
			</div>
			<div class="clefa-settings-panel-content" data-clefa-role="panel-content" style="display:none;">
				<div class="clefa-panel-header" data-clefa-role="panel-header">
					<span class="clefa-panel-title" data-clefa-role="panel-title"></span>
					<button type="button" class="clefa-panel-close" data-clefa-action="close-panel" title="<?php esc_attr_e( 'Close', 'codelinden-elementor-form-addon' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="clefa-panel-sections" data-clefa-role="panel-sections">
					<!-- Accordion sections rendered by JS -->
				</div>
			</div>
		</aside>

	</div>

	<!-- Simulate Modal -->
	<div class="clefa-simulate-modal" data-clefa-role="simulate-modal" aria-hidden="true">
		<div class="clefa-simulate-backdrop" data-clefa-action="close-simulate"></div>
		<div class="clefa-simulate-container">
			<div class="clefa-simulate-topbar">
				<span class="clefa-simulate-title">
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Form Simulation', 'codelinden-elementor-form-addon' ); ?>
				</span>
				<div class="clefa-simulate-viewport-switcher">
					<button type="button" class="clefa-simulate-device clefa-simulate-device-active" data-clefa-device="desktop" title="<?php esc_attr_e( 'Desktop', 'codelinden-elementor-form-addon' ); ?>">
						<span class="dashicons dashicons-desktop"></span>
					</button>
					<button type="button" class="clefa-simulate-device" data-clefa-device="tablet" title="<?php esc_attr_e( 'Tablet', 'codelinden-elementor-form-addon' ); ?>">
						<span class="dashicons dashicons-tablet"></span>
					</button>
					<button type="button" class="clefa-simulate-device" data-clefa-device="mobile" title="<?php esc_attr_e( 'Mobile', 'codelinden-elementor-form-addon' ); ?>">
						<span class="dashicons dashicons-smartphone"></span>
					</button>
				</div>
				<button type="button" class="clefa-btn clefa-btn-sm clefa-btn-ghost" data-clefa-action="close-simulate">
					<span class="dashicons dashicons-no-alt"></span>
					<?php esc_html_e( 'Close', 'codelinden-elementor-form-addon' ); ?>
				</button>
			</div>
			<div class="clefa-simulate-body" data-clefa-role="simulate-body">
				<div class="clefa-simulate-frame-wrap" data-clefa-device="desktop">
					<div class="clefa-simulate-form-preview" data-clefa-role="form-preview">
						<!-- Rendered by JS -->
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Action Picker Modal -->
	<div class="clefa-modal" data-clefa-role="action-picker-modal" aria-hidden="true">
		<div class="clefa-modal-backdrop" data-clefa-action="close-modal"></div>
		<div class="clefa-modal-container">
			<div class="clefa-modal-header">
				<h2><?php esc_html_e( 'Add Action', 'codelinden-elementor-form-addon' ); ?></h2>
				<button type="button" class="clefa-modal-close" data-clefa-action="close-modal">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<div class="clefa-modal-body">
				<div class="clefa-action-picker-grid" data-clefa-role="action-picker-grid">
					<!-- Action types rendered by JS -->
				</div>
			</div>
		</div>
	</div>

</div>
