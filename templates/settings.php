<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap ucm-wrap">
	<?php $current_page = 'settings'; include UCM_PATH . 'templates/partials/navigation.php'; ?>

	<section class="ucm-page-hero ucm-page-hero-settings">
		<div class="ucm-page-hero__content">
			<span class="ucm-eyebrow"><?php esc_html_e( 'Settings', 'universal-cpt-migrator' ); ?></span>
			<div class="ucm-header">
				<div>
					<h1><?php esc_html_e( 'Operational Controls', 'universal-cpt-migrator' ); ?></h1>
					<p class="ucm-page-intro"><?php esc_html_e( 'Tune throughput, retention, remote media policy, and uninstall cleanup without digging through a flat wall of fields.', 'universal-cpt-migrator' ); ?></p>
				</div>
			</div>
		</div>
		<div class="ucm-page-hero__aside">
			<div class="ucm-hero-panel">
				<strong><?php esc_html_e( 'Safe defaults first', 'universal-cpt-migrator' ); ?></strong>
				<p><?php esc_html_e( 'Use these controls to balance job throughput, storage retention, remote media access, and uninstall posture for your environment.', 'universal-cpt-migrator' ); ?></p>
			</div>
		</div>
	</section>

	<form method="post" action="options.php" class="ucm-card ucm-workflow-card">
		<?php settings_fields( 'ucm_settings_group' ); ?>
		<div class="ucm-card-heading">
			<div>
				<h2><?php esc_html_e( 'Operational Controls', 'universal-cpt-migrator' ); ?></h2>
				<p class="ucm-text-light"><?php esc_html_e( 'Settings are grouped by throughput, retention, media policy, and uninstall behavior.', 'universal-cpt-migrator' ); ?></p>
			</div>
		</div>
		<div class="ucm-settings-grid">
			<section class="ucm-settings-section">
				<h3><?php esc_html_e( 'Throughput', 'universal-cpt-migrator' ); ?></h3>
				<div class="ucm-form-row">
					<label class="ucm-label" for="ucm_chunk_size"><?php esc_html_e( 'Chunk size', 'universal-cpt-migrator' ); ?></label>
					<input class="ucm-input" id="ucm_chunk_size" type="number" min="1" max="250" name="ucm_settings[chunk_size]" value="<?php echo esc_attr( $values['chunk_size'] ); ?>">
					<p class="ucm-text-light"><?php esc_html_e( 'Lower values reduce memory pressure. Higher values finish faster on stable infrastructure.', 'universal-cpt-migrator' ); ?></p>
				</div>
			</section>
			<section class="ucm-settings-section">
				<h3><?php esc_html_e( 'Retention', 'universal-cpt-migrator' ); ?></h3>
				<div class="ucm-form-row">
					<label class="ucm-label" for="ucm_log_retention"><?php esc_html_e( 'Log retention (days)', 'universal-cpt-migrator' ); ?></label>
					<input class="ucm-input" id="ucm_log_retention" type="number" min="1" max="365" name="ucm_settings[log_retention_days]" value="<?php echo esc_attr( $values['log_retention_days'] ); ?>">
				</div>
				<div class="ucm-form-row">
					<label class="ucm-label" for="ucm_artifact_retention"><?php esc_html_e( 'Export/import artifact retention (days)', 'universal-cpt-migrator' ); ?></label>
					<input class="ucm-input" id="ucm_artifact_retention" type="number" min="1" max="365" name="ucm_settings[artifact_retention_days]" value="<?php echo esc_attr( $values['artifact_retention_days'] ); ?>">
				</div>
				<div class="ucm-form-row">
					<label class="ucm-label" for="ucm_temp_retention"><?php esc_html_e( 'Temporary extraction retention (days)', 'universal-cpt-migrator' ); ?></label>
					<input class="ucm-input" id="ucm_temp_retention" type="number" min="1" max="60" name="ucm_settings[temp_retention_days]" value="<?php echo esc_attr( $values['temp_retention_days'] ); ?>">
				</div>
				<div class="ucm-form-row">
					<label class="ucm-label" for="ucm_job_retention"><?php esc_html_e( 'Background job retention (days)', 'universal-cpt-migrator' ); ?></label>
					<input class="ucm-input" id="ucm_job_retention" type="number" min="1" max="365" name="ucm_settings[job_retention_days]" value="<?php echo esc_attr( $values['job_retention_days'] ); ?>">
				</div>
			</section>
			<section class="ucm-settings-section">
				<h3><?php esc_html_e( 'Media Policy', 'universal-cpt-migrator' ); ?></h3>
				<div class="ucm-form-row">
					<label class="ucm-checkbox-row"><input id="ucm_allow_remote_media" type="checkbox" name="ucm_settings[allow_remote_media]" value="1" <?php checked( ! empty( $values['allow_remote_media'] ) ); ?>> <?php esc_html_e( 'Allow remote media imports for allowlisted hosts.', 'universal-cpt-migrator' ); ?></label>
				</div>
				<div class="ucm-form-row">
					<label class="ucm-label" for="ucm_allowed_media_hosts"><?php esc_html_e( 'Allowed media hosts', 'universal-cpt-migrator' ); ?></label>
					<textarea class="ucm-input" id="ucm_allowed_media_hosts" rows="6" name="ucm_settings[allowed_media_hosts]"><?php echo esc_textarea( $values['allowed_media_hosts'] ); ?></textarea>
					<p class="ucm-text-light"><?php esc_html_e( 'One host per line. Remote media stays blocked unless it is explicitly allowed here.', 'universal-cpt-migrator' ); ?></p>
				</div>
			</section>
			<section class="ucm-settings-section">
				<h3><?php esc_html_e( 'Cleanup Policy', 'universal-cpt-migrator' ); ?></h3>
				<div class="ucm-form-row">
					<label class="ucm-checkbox-row"><input type="checkbox" name="ucm_settings[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $values['delete_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete plugin data and storage on uninstall.', 'universal-cpt-migrator' ); ?></label>
					<p class="ucm-text-light"><?php esc_html_e( 'Keep this disabled unless you explicitly want exports, imports, logs, and job records removed during uninstall.', 'universal-cpt-migrator' ); ?></p>
				</div>
			</section>
		</div>
		<?php submit_button( __( 'Save Settings', 'universal-cpt-migrator' ) ); ?>
	</form>
</div>
