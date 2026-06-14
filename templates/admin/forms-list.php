<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap clefa-admin-page clefa-forms-list-page">
	<?php CLEFA_Admin_UI::settings_messages( 'clefa_forms' ); ?>

	<h1 class="clefa-page-title">
		<span class="dashicons dashicons-feedback"></span>
		<?php esc_html_e( 'Forms', 'codelinden-elementor-form-addon' ); ?>
	</h1>

	<div class="clefa-page-toolbar">
		<button type="button"
		        class="clefa-btn clefa-btn-primary"
		        id="clefa-open-template-picker">
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'Add New Form', 'codelinden-elementor-form-addon' ); ?>
		</button>
	</div>

	<?php if ( empty( $forms ) ) : ?>
		<div class="clefa-empty-state">
			<span class="dashicons dashicons-feedback clefa-empty-icon"></span>
			<h2><?php esc_html_e( 'No forms yet', 'codelinden-elementor-form-addon' ); ?></h2>
			<p><?php esc_html_e( 'Create your first form to get started.', 'codelinden-elementor-form-addon' ); ?></p>
			<button type="button"
			        class="clefa-btn clefa-btn-primary clefa-btn-lg"
			        id="clefa-open-template-picker-empty">
				<?php esc_html_e( 'Create First Form', 'codelinden-elementor-form-addon' ); ?>
			</button>
		</div>
	<?php else : ?>
		<div class="clefa-forms-grid">
			<?php foreach ( $forms as $form ) :
				$feature_map  = ! empty( $form['feature_map_json'] ) ? json_decode( $form['feature_map_json'], true ) : array();
				$is_published = 'published' === $form['status'];
				$edit_url     = admin_url( 'admin.php?page=clefa-edit-form&form_id=' . absint( $form['id'] ) );
			?>
			<div class="clefa-form-card" data-clefa-form-id="<?php echo esc_attr( $form['id'] ); ?>">
				<div class="clefa-form-card-body">
					<div class="clefa-form-card-title-row">
						<h3 class="clefa-form-card-title">
							<a href="<?php echo esc_url( $edit_url ); ?>">
								<?php echo esc_html( $form['form_name'] ); ?>
							</a>
						</h3>
						<span class="clefa-badge <?php echo $is_published ? 'clefa-badge-success' : 'clefa-badge-draft'; ?>">
							<?php echo $is_published ? esc_html__( 'Published', 'codelinden-elementor-form-addon' ) : esc_html__( 'Draft', 'codelinden-elementor-form-addon' ); ?>
						</span>
					</div>

					<div class="clefa-form-card-meta">
						<span class="clefa-form-meta-item">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php echo esc_html( ucfirst( $form['form_type'] ) ); ?>
						</span>
						<span class="clefa-form-meta-item">
							<span class="dashicons dashicons-backup"></span>
							<?php printf( esc_html__( 'v%d', 'codelinden-elementor-form-addon' ), absint( $form['version'] ) ); ?>
						</span>
						<span class="clefa-form-meta-item">
							<span class="dashicons dashicons-calendar-alt"></span>
							<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $form['updated_at'] ) ) ); ?>
						</span>
					</div>

					<?php if ( ! empty( $feature_map ) ) : ?>
					<div class="clefa-form-features">
						<?php if ( ! empty( $feature_map['has_steps'] ) ) : ?><span class="clefa-feature-chip"><?php esc_html_e( 'Multi-step', 'codelinden-elementor-form-addon' ); ?></span><?php endif; ?>
						<?php if ( ! empty( $feature_map['has_uploads'] ) ) : ?><span class="clefa-feature-chip"><?php esc_html_e( 'Uploads', 'codelinden-elementor-form-addon' ); ?></span><?php endif; ?>
						<?php if ( ! empty( $feature_map['has_conditions'] ) ) : ?><span class="clefa-feature-chip"><?php esc_html_e( 'Conditions', 'codelinden-elementor-form-addon' ); ?></span><?php endif; ?>
						<?php if ( ! empty( $feature_map['has_repeater'] ) ) : ?><span class="clefa-feature-chip"><?php esc_html_e( 'Repeater', 'codelinden-elementor-form-addon' ); ?></span><?php endif; ?>
						<?php if ( ! empty( $feature_map['field_count'] ) ) : ?><span class="clefa-feature-chip"><?php printf( esc_html__( '%d fields', 'codelinden-elementor-form-addon' ), absint( $feature_map['field_count'] ) ); ?></span><?php endif; ?>
					</div>
					<?php endif; ?>
				</div>

				<div class="clefa-form-card-actions">
					<a href="<?php echo esc_url( $edit_url ); ?>" class="clefa-btn clefa-btn-sm clefa-btn-secondary">
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e( 'Edit', 'codelinden-elementor-form-addon' ); ?>
					</a>
					<button type="button"
					        class="clefa-btn clefa-btn-sm clefa-btn-ghost"
					        data-clefa-action="duplicate-form"
					        data-clefa-form-id="<?php echo esc_attr( $form['id'] ); ?>">
						<span class="dashicons dashicons-admin-page"></span>
						<?php esc_html_e( 'Duplicate', 'codelinden-elementor-form-addon' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=clefa-submissions&form_id=' . absint( $form['id'] ) ) ); ?>"
					   class="clefa-btn clefa-btn-sm clefa-btn-ghost">
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'Submissions', 'codelinden-elementor-form-addon' ); ?>
					</a>
					<button type="button"
					        class="clefa-btn clefa-btn-sm clefa-btn-danger-ghost"
					        data-clefa-action="delete-form"
					        data-clefa-form-id="<?php echo esc_attr( $form['id'] ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<!-- ========================================================
     Form Template Picker Modal
     ======================================================== -->
<div id="clefa-template-picker-modal" class="clefa-tpl-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Choose a Form Template', 'codelinden-elementor-form-addon' ); ?>" hidden>
	<div class="clefa-tpl-modal-backdrop" id="clefa-tpl-backdrop"></div>
	<div class="clefa-tpl-modal-box">
		<button type="button" class="clefa-tpl-modal-close" id="clefa-tpl-close" aria-label="<?php esc_attr_e( 'Close', 'codelinden-elementor-form-addon' ); ?>">
			<span class="dashicons dashicons-no-alt"></span>
		</button>
		<h2 class="clefa-tpl-modal-title"><?php esc_html_e( 'Choose a Starting Point', 'codelinden-elementor-form-addon' ); ?></h2>
		<p class="clefa-tpl-modal-subtitle"><?php esc_html_e( 'Pick a template or start with a blank form. You can customise everything in the builder.', 'codelinden-elementor-form-addon' ); ?></p>

		<div class="clefa-tpl-grid" id="clefa-tpl-grid">
			<?php
			foreach ( CLEFA_Form_Templates::all() as $key => $tpl ) :
			?>
			<button
				type="button"
				class="clefa-tpl-card"
				data-clefa-template="<?php echo esc_attr( $key ); ?>"
				data-clefa-form-type="<?php echo esc_attr( $tpl['form_type'] ); ?>">
				<span class="clefa-tpl-icon dashicons <?php echo esc_attr( $tpl['icon'] ); ?>"></span>
				<span class="clefa-tpl-label"><?php echo esc_html( $tpl['label'] ); ?></span>
				<span class="clefa-tpl-desc"><?php echo esc_html( $tpl['description'] ); ?></span>
			</button>
			<?php endforeach; ?>
		</div>

		<div class="clefa-tpl-form-name-row" id="clefa-tpl-name-row" hidden>
			<label for="clefa-tpl-name-input" class="clefa-tpl-name-label">
				<?php esc_html_e( 'Form Name', 'codelinden-elementor-form-addon' ); ?>
			</label>
			<input
				type="text"
				id="clefa-tpl-name-input"
				class="clefa-tpl-name-input"
				placeholder="<?php esc_attr_e( 'e.g. Contact Us', 'codelinden-elementor-form-addon' ); ?>"
			>
			<button type="button" class="clefa-btn clefa-btn-primary clefa-btn-lg" id="clefa-tpl-create-btn">
				<?php esc_html_e( 'Create Form', 'codelinden-elementor-form-addon' ); ?>
			</button>
		</div>
	</div>
</div>
