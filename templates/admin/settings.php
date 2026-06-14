<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap clefa-admin-page clefa-settings-page">
	<?php CLEFA_Admin_UI::settings_messages( 'clefa_settings' ); ?>

	<h1 class="clefa-page-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Form Addon Settings', 'codelinden-elementor-form-addon' ); ?>
	</h1>

	<form method="post" action="" class="clefa-settings-form-wrap">
		<?php wp_nonce_field( 'clefa_save_settings', 'clefa_settings_nonce' ); ?>

		<div class="clefa-settings-layout">
			<div class="clefa-settings-main">

				<div class="clefa-card">
					<div class="clefa-card-header">
						<h2><?php esc_html_e( 'Submission Settings', 'codelinden-elementor-form-addon' ); ?></h2>
					</div>
					<div class="clefa-card-body">
						<div class="clefa-field-row">
							<label for="clefa_default_redirect_url"><?php esc_html_e( 'Default Redirect URL', 'codelinden-elementor-form-addon' ); ?></label>
							<input type="url" id="clefa_default_redirect_url" name="clefa_default_redirect_url"
							       value="<?php echo esc_url( get_option( 'clefa_default_redirect_url', '' ) ); ?>"
							       placeholder="https://" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Where to send users after form submission (if no form-specific redirect is set).', 'codelinden-elementor-form-addon' ); ?></p>
						</div>
						<div class="clefa-field-row">
							<label for="clefa_default_success_message"><?php esc_html_e( 'Default Success Message', 'codelinden-elementor-form-addon' ); ?></label>
							<input type="text" id="clefa_default_success_message" name="clefa_default_success_message"
							       value="<?php echo esc_attr( get_option( 'clefa_default_success_message', '' ) ); ?>"
							       class="large-text" />
						</div>
						<div class="clefa-field-row">
							<label for="clefa_default_error_message"><?php esc_html_e( 'Default Error Message', 'codelinden-elementor-form-addon' ); ?></label>
							<input type="text" id="clefa_default_error_message" name="clefa_default_error_message"
							       value="<?php echo esc_attr( get_option( 'clefa_default_error_message', '' ) ); ?>"
							       class="large-text" />
						</div>
					</div>
				</div>

				<div class="clefa-card">
					<div class="clefa-card-header">
						<h2><?php esc_html_e( 'Upload Settings', 'codelinden-elementor-form-addon' ); ?></h2>
					</div>
					<div class="clefa-card-body">
						<div class="clefa-field-row">
							<label for="clefa_upload_max_size"><?php esc_html_e( 'Max Upload Size (MB)', 'codelinden-elementor-form-addon' ); ?></label>
							<input type="number" id="clefa_upload_max_size" name="clefa_upload_max_size"
							       value="<?php echo esc_attr( get_option( 'clefa_upload_max_size', 5 ) ); ?>"
							       min="1" max="100" class="small-text" />
						</div>
						<div class="clefa-field-row">
							<label for="clefa_upload_allowed_types"><?php esc_html_e( 'Allowed File Types', 'codelinden-elementor-form-addon' ); ?></label>
							<input type="text" id="clefa_upload_allowed_types" name="clefa_upload_allowed_types"
							       value="<?php echo esc_attr( get_option( 'clefa_upload_allowed_types', 'jpg,jpeg,png,gif,pdf,doc,docx' ) ); ?>"
							       class="regular-text" />
							<p class="description"><?php esc_html_e( 'Comma-separated extensions.', 'codelinden-elementor-form-addon' ); ?></p>
						</div>
						<div class="clefa-field-row">
							<label for="clefa_temp_upload_expiry"><?php esc_html_e( 'Temp Upload Expiry (hours)', 'codelinden-elementor-form-addon' ); ?></label>
							<input type="number" id="clefa_temp_upload_expiry" name="clefa_temp_upload_expiry"
							       value="<?php echo esc_attr( get_option( 'clefa_temp_upload_expiry', 24 ) ); ?>"
							       min="1" max="168" class="small-text" />
						</div>
					</div>
				</div>

				<div class="clefa-card">
					<div class="clefa-card-header">
						<h2><?php esc_html_e( 'Security & Anti-Spam', 'codelinden-elementor-form-addon' ); ?></h2>
					</div>
					<div class="clefa-card-body">
						<div class="clefa-field-row clefa-field-row-checkbox">
							<label>
								<input type="checkbox" name="clefa_enable_antispam" value="1"
								       <?php checked( get_option( 'clefa_enable_antispam', true ) ); ?> />
								<?php esc_html_e( 'Enable anti-spam (honeypot) by default', 'codelinden-elementor-form-addon' ); ?>
							</label>
						</div>
						<div class="clefa-field-row clefa-field-row-checkbox">
							<label>
								<input type="checkbox" name="clefa_enable_nonce_refresh" value="1"
								       <?php checked( get_option( 'clefa_enable_nonce_refresh', true ) ); ?> />
								<?php esc_html_e( 'Enable dynamic nonce refresh (recommended for cached sites)', 'codelinden-elementor-form-addon' ); ?>
							</label>
						</div>
					</div>
				</div>

				<div class="clefa-card">
					<div class="clefa-card-header">
						<h2><?php esc_html_e( 'Storage & Cleanup', 'codelinden-elementor-form-addon' ); ?></h2>
					</div>
					<div class="clefa-card-body">
						<div class="clefa-field-row clefa-field-row-checkbox">
							<label>
								<input type="checkbox" name="clefa_enable_submission_storage" value="1"
								       <?php checked( get_option( 'clefa_enable_submission_storage', true ) ); ?> />
								<?php esc_html_e( 'Store submissions in database by default', 'codelinden-elementor-form-addon' ); ?>
							</label>
						</div>
						<div class="clefa-field-row clefa-field-row-checkbox">
							<label>
								<input type="checkbox" name="clefa_enable_cleanup_schedule" value="1"
								       <?php checked( get_option( 'clefa_enable_cleanup_schedule', true ) ); ?> />
								<?php esc_html_e( 'Enable scheduled cleanup of expired temporary uploads', 'codelinden-elementor-form-addon' ); ?>
							</label>
						</div>
					</div>
				</div>

				<div class="clefa-card">
					<div class="clefa-card-header">
						<h2><?php esc_html_e( 'Developer', 'codelinden-elementor-form-addon' ); ?></h2>
					</div>
					<div class="clefa-card-body">
						<div class="clefa-field-row clefa-field-row-checkbox">
							<label>
								<input type="checkbox" name="clefa_enable_debug_console" value="1"
								       <?php checked( get_option( 'clefa_enable_debug_console', false ) ); ?> />
								<?php esc_html_e( 'Enable debug console events (helpful in development)', 'codelinden-elementor-form-addon' ); ?>
							</label>
						</div>
						<div class="clefa-field-row">
							<label><?php esc_html_e( 'Default Style Mode', 'codelinden-elementor-form-addon' ); ?></label>
							<select name="clefa_default_style_mode">
								<option value="inherited" <?php selected( get_option( 'clefa_default_style_mode', 'inherited' ), 'inherited' ); ?>>
									<?php esc_html_e( 'Inherited (use theme styles)', 'codelinden-elementor-form-addon' ); ?>
								</option>
								<option value="custom" <?php selected( get_option( 'clefa_default_style_mode', 'inherited' ), 'custom' ); ?>>
									<?php esc_html_e( 'Custom (full Elementor style controls)', 'codelinden-elementor-form-addon' ); ?>
								</option>
							</select>
						</div>
					</div>
				</div>

				<div class="clefa-settings-actions">
					<button type="submit" class="clefa-btn clefa-btn-primary clefa-btn-lg">
						<?php esc_html_e( 'Save Settings', 'codelinden-elementor-form-addon' ); ?>
					</button>
				</div>

			</div>

			<div class="clefa-settings-sidebar">
				<div class="clefa-card">
					<div class="clefa-card-header">
						<h3><?php esc_html_e( 'Quick Links', 'codelinden-elementor-form-addon' ); ?></h3>
					</div>
					<div class="clefa-card-body">
						<ul class="clefa-quick-links">
							<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=clefa-forms' ) ); ?>"><?php esc_html_e( 'All Forms', 'codelinden-elementor-form-addon' ); ?></a></li>
							<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=clefa-submissions' ) ); ?>"><?php esc_html_e( 'Submissions', 'codelinden-elementor-form-addon' ); ?></a></li>
							<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=clefa-dev' ) ); ?>"><?php esc_html_e( 'Dev / Test Suite', 'codelinden-elementor-form-addon' ); ?></a></li>
						</ul>
					</div>
				</div>
				<div class="clefa-card">
					<div class="clefa-card-header">
						<h3><?php esc_html_e( 'Plugin Info', 'codelinden-elementor-form-addon' ); ?></h3>
					</div>
					<div class="clefa-card-body">
						<p class="clefa-text-sm"><strong><?php esc_html_e( 'Version:', 'codelinden-elementor-form-addon' ); ?></strong> <?php echo esc_html( CLEFA_PLUGIN_VERSION ); ?></p>
						<p class="clefa-text-sm"><strong><?php esc_html_e( 'DB Version:', 'codelinden-elementor-form-addon' ); ?></strong> <?php echo esc_html( CLEFA_DB_VERSION ); ?></p>
					</div>
				</div>
			</div>
		</div>

	</form>
</div>
