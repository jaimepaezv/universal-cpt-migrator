<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap ucm-wrap" data-ucm-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-ucm-nonce="<?php echo esc_attr( wp_create_nonce( 'ucm_admin_nonce' ) ); ?>">
	<?php $current_page = 'diagnostics'; include UCM_PATH . 'templates/partials/navigation.php'; ?>

	<section class="ucm-page-hero ucm-page-hero-diagnostics">
		<div class="ucm-page-hero__content">
			<span class="ucm-eyebrow"><?php esc_html_e( 'Diagnostics', 'universal-cpt-migrator' ); ?></span>
			<div class="ucm-header">
				<div>
					<h1><?php esc_html_e( 'Diagnostics & Health', 'universal-cpt-migrator' ); ?></h1>
					<p class="ucm-page-intro"><?php esc_html_e( 'Inspect queue health, repair missing worker schedules, review failures, and move directly into the right remediation path.', 'universal-cpt-migrator' ); ?></p>
				</div>
			</div>
		</div>
		<div class="ucm-page-hero__aside">
			<div class="ucm-hero-panel">
				<strong><?php esc_html_e( 'Triage hub', 'universal-cpt-migrator' ); ?></strong>
				<p><?php esc_html_e( 'This screen is the operator command center for stalled jobs, failed media, export artifacts, and retention cleanup.', 'universal-cpt-migrator' ); ?></p>
			</div>
		</div>
	</section>

	<?php if ( 'cleanup-run' === $notice ) : ?>
		<div class="ucm-notice ucm-notice-success"><p><?php esc_html_e( 'Retention cleanup was run successfully.', 'universal-cpt-migrator' ); ?></p></div>
	<?php elseif ( 'worker-checked' === $notice ) : ?>
		<div class="ucm-notice ucm-notice-success"><p><?php echo esc_html( sprintf( __( 'Worker sanity check completed. Repaired %1$d missing worker events. Cleanup schedule repaired: %2$s.', 'universal-cpt-migrator' ), (int) $notice_args['worker_events'], ! empty( $notice_args['cleanup_hook'] ) ? __( 'yes', 'universal-cpt-migrator' ) : __( 'no', 'universal-cpt-migrator' ) ) ); ?></p></div>
	<?php elseif ( 'stale-cleaned' === $notice ) : ?>
		<div class="ucm-notice ucm-notice-success"><p><?php echo esc_html( sprintf( __( 'Removed %d stale queued jobs and their retained resources.', 'universal-cpt-migrator' ), (int) $notice_args['stale_jobs'] ) ); ?></p></div>
	<?php endif; ?>

	<div class="ucm-card ucm-workflow-card">
		<h3><?php esc_html_e( 'Actions', 'universal-cpt-migrator' ); ?></h3>
		<p class="ucm-text-light"><?php esc_html_e( 'Use these actions to repair scheduling, clean retention data, or remove queued jobs that have clearly stalled.', 'universal-cpt-migrator' ); ?></p>
		<div class="ucm-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ucm_run_cleanup_now">
				<?php wp_nonce_field( 'ucm_run_cleanup_now' ); ?>
				<button type="submit" class="ucm-btn"><?php esc_html_e( 'Run Retention Cleanup', 'universal-cpt-migrator' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ucm_run_cron_sanity">
				<?php wp_nonce_field( 'ucm_run_cron_sanity' ); ?>
				<button type="submit" class="ucm-btn ucm-btn-secondary"><?php esc_html_e( 'Run Worker Sanity Check', 'universal-cpt-migrator' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ucm_cleanup_stale_jobs">
				<?php wp_nonce_field( 'ucm_cleanup_stale_jobs' ); ?>
				<button type="submit" class="ucm-btn ucm-btn-ghost"><?php esc_html_e( 'Clear Stale Queued Jobs', 'universal-cpt-migrator' ); ?></button>
			</form>
		</div>
	</div>

	<div class="ucm-grid">
		<div class="ucm-card ucm-workflow-card">
			<h3><?php esc_html_e( 'Cron Health', 'universal-cpt-migrator' ); ?></h3>
			<p><?php echo esc_html( $snapshot['cron_disabled'] ? __( 'WP-Cron is disabled.', 'universal-cpt-migrator' ) : __( 'WP-Cron is enabled.', 'universal-cpt-migrator' ) ); ?></p>
			<p><?php echo esc_html( $snapshot['next_cleanup'] ? gmdate( 'Y-m-d H:i:s', $snapshot['next_cleanup'] ) . ' UTC' : __( 'No cleanup event scheduled.', 'universal-cpt-migrator' ) ); ?></p>
			<p><?php echo esc_html( $snapshot['next_export_worker'] ? gmdate( 'Y-m-d H:i:s', $snapshot['next_export_worker'] ) . ' UTC' : __( 'No export worker queued.', 'universal-cpt-migrator' ) ); ?></p>
			<p><?php echo esc_html( $snapshot['next_import_worker'] ? gmdate( 'Y-m-d H:i:s', $snapshot['next_import_worker'] ) . ' UTC' : __( 'No import worker queued.', 'universal-cpt-migrator' ) ); ?></p>
		</div>
		<div class="ucm-card ucm-workflow-card">
			<h3><?php esc_html_e( 'Queue Health', 'universal-cpt-migrator' ); ?></h3>
			<p><?php printf( esc_html__( 'Total jobs: %d', 'universal-cpt-migrator' ), (int) $snapshot['total_jobs'] ); ?></p>
			<p><?php printf( esc_html__( 'Queued jobs: %d', 'universal-cpt-migrator' ), (int) $snapshot['queued_jobs'] ); ?></p>
			<p><?php printf( esc_html__( 'Running jobs: %d', 'universal-cpt-migrator' ), (int) $snapshot['running_jobs'] ); ?></p>
			<p><?php printf( esc_html__( 'Failed jobs: %d', 'universal-cpt-migrator' ), (int) $snapshot['failed_jobs'] ); ?></p>
			<p><?php printf( esc_html__( 'Completed jobs: %d', 'universal-cpt-migrator' ), (int) $snapshot['completed_jobs'] ); ?></p>
			<p><?php printf( esc_html__( 'Stale queued jobs: %d', 'universal-cpt-migrator' ), (int) $snapshot['stale_queued_jobs'] ); ?></p>
			<p><?php printf( esc_html__( 'Stale running jobs: %d', 'universal-cpt-migrator' ), (int) $snapshot['stale_running_jobs'] ); ?></p>
			<p><?php printf( esc_html__( 'Queued jobs missing worker events: %d', 'universal-cpt-migrator' ), (int) $snapshot['unscheduled_queued'] ); ?></p>
		</div>
		<div class="ucm-card ucm-workflow-card">
			<h3><?php esc_html_e( 'Failure Breakdown', 'universal-cpt-migrator' ); ?></h3>
			<?php if ( empty( $snapshot['failure_breakdown'] ) ) : ?>
				<p class="ucm-text-light"><?php esc_html_e( 'No failure categories currently require attention.', 'universal-cpt-migrator' ); ?></p>
			<?php else : ?>
				<ul class="ucm-detail-list">
					<?php foreach ( $snapshot['failure_breakdown'] as $category => $count ) : ?>
						<li><?php echo esc_html( sprintf( __( '%1$s: %2$d', 'universal-cpt-migrator' ), ucfirst( $category ), (int) $count ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="ucm-card ucm-workflow-card">
		<h3><?php esc_html_e( 'Operator Guidance', 'universal-cpt-migrator' ); ?></h3>
		<div class="ucm-guidance-grid">
			<?php foreach ( $snapshot['recommendations'] as $recommendation ) : ?>
				<div class="ucm-guidance-card ucm-guidance-<?php echo esc_attr( $recommendation['severity'] ); ?>">
					<strong><?php echo esc_html( $recommendation['title'] ); ?></strong>
					<p><?php echo esc_html( $recommendation['message'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="ucm-card ucm-workflow-card">
		<h3><?php esc_html_e( 'Jobs Requiring Attention', 'universal-cpt-migrator' ); ?></h3>
		<?php if ( empty( $snapshot['attention_rows'] ) ) : ?>
			<p class="ucm-text-light"><?php esc_html_e( 'No failed, stale, or unscheduled jobs currently require operator action.', 'universal-cpt-migrator' ); ?></p>
		<?php else : ?>
			<div class="ucm-job-table">
				<div class="ucm-job-table-head">
					<span><?php esc_html_e( 'Job', 'universal-cpt-migrator' ); ?></span>
					<span><?php esc_html_e( 'State', 'universal-cpt-migrator' ); ?></span>
					<span><?php esc_html_e( 'Details', 'universal-cpt-migrator' ); ?></span>
					<span><?php esc_html_e( 'Next Action', 'universal-cpt-migrator' ); ?></span>
				</div>
				<?php foreach ( $snapshot['attention_rows'] as $job ) : ?>
					<div class="ucm-job-table-row">
						<div>
							<strong><?php echo esc_html( $job['job_id'] ); ?></strong>
							<div class="ucm-text-light"><?php echo esc_html( $job['type'] ); ?></div>
						</div>
						<div>
							<span class="ucm-pill ucm-pill-<?php echo esc_attr( $job['status'] ); ?>"><?php echo esc_html( $job['status'] ); ?></span>
							<div class="ucm-text-light"><?php echo esc_html( $job['age_label'] ); ?></div>
							<?php if ( $job['stage'] ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Stage: %s', 'universal-cpt-migrator' ), $job['stage'] ) ); ?></div>
							<?php endif; ?>
							<?php if ( $job['failure_category'] ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Category: %s', 'universal-cpt-migrator' ), $job['failure_category'] ) ); ?></div>
							<?php endif; ?>
							<?php if ( $job['failure_subsystem'] ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Subsystem: %s', 'universal-cpt-migrator' ), $job['failure_subsystem'] ) ); ?></div>
							<?php endif; ?>
						</div>
						<div>
							<?php if ( $job['error'] ) : ?>
								<div><?php echo esc_html( $job['error'] ); ?></div>
								<?php if ( $job['error_code'] ) : ?>
									<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Error code: %s', 'universal-cpt-migrator' ), $job['error_code'] ) ); ?></div>
								<?php endif; ?>
								<?php if ( ! empty( $job['error_context'] ) ) : ?>
									<div class="ucm-text-light"><?php echo esc_html( wp_json_encode( $job['error_context'] ) ); ?></div>
								<?php endif; ?>
								<?php if ( ! empty( $job['remediation_key'] ) ) : ?>
									<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Remediation key: %s', 'universal-cpt-migrator' ), $job['remediation_key'] ) ); ?></div>
								<?php endif; ?>
							<?php else : ?>
								<div><?php echo esc_html( sprintf( __( 'Progress: %1$d%%, offset %2$d of %3$d', 'universal-cpt-migrator' ), (int) $job['progress'], (int) $job['offset'], (int) $job['items_total'] ) ); ?></div>
								<?php if ( $job['failed_items'] ) : ?>
									<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Failed items: %d', 'universal-cpt-migrator' ), (int) $job['failed_items'] ) ); ?></div>
								<?php endif; ?>
								<?php if ( $job['warning_items'] ) : ?>
									<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Warnings: %d', 'universal-cpt-migrator' ), (int) $job['warning_items'] ) ); ?></div>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( $job['log_path'] ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( $job['log_path'] ); ?></div>
							<?php endif; ?>
						</div>
						<div>
							<div><?php echo esc_html( $job['recommended_action'] ); ?></div>
							<?php if ( isset( $job['retryable'] ) ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( $job['retryable'] ? __( 'Retryable after remediation', 'universal-cpt-migrator' ) : __( 'Manual review required before retry', 'universal-cpt-migrator' ) ); ?></div>
							<?php endif; ?>
							<div class="ucm-inline-links">
								<?php if ( $job['resume_url'] ) : ?>
									<a href="<?php echo esc_url( $job['resume_url'] ); ?>"><?php esc_html_e( 'Open import job', 'universal-cpt-migrator' ); ?></a>
								<?php endif; ?>
								<?php if ( $job['download_url'] ) : ?>
									<a href="<?php echo esc_url( $job['download_url'] ); ?>"><?php esc_html_e( 'Download export', 'universal-cpt-migrator' ); ?></a>
								<?php endif; ?>
								<?php if ( $job['log_url'] ) : ?>
									<a href="<?php echo esc_url( $job['log_url'] ); ?>"><?php esc_html_e( 'Open log preview', 'universal-cpt-migrator' ); ?></a>
								<?php endif; ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-logs' ) ); ?>"><?php esc_html_e( 'Open logs', 'universal-cpt-migrator' ); ?></a>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="ucm-card ucm-workflow-card">
		<h3><?php esc_html_e( 'Recent Jobs', 'universal-cpt-migrator' ); ?></h3>
		<?php if ( empty( $snapshot['job_rows'] ) ) : ?>
			<p class="ucm-text-light"><?php esc_html_e( 'No jobs recorded.', 'universal-cpt-migrator' ); ?></p>
		<?php else : ?>
			<div class="ucm-job-table">
				<div class="ucm-job-table-head">
					<span><?php esc_html_e( 'Job', 'universal-cpt-migrator' ); ?></span>
					<span><?php esc_html_e( 'State', 'universal-cpt-migrator' ); ?></span>
					<span><?php esc_html_e( 'Details', 'universal-cpt-migrator' ); ?></span>
					<span><?php esc_html_e( 'Action', 'universal-cpt-migrator' ); ?></span>
				</div>
				<?php foreach ( $snapshot['job_rows'] as $job ) : ?>
					<div class="ucm-job-table-row">
						<div>
							<strong><?php echo esc_html( $job['job_id'] ); ?></strong>
							<div class="ucm-text-light"><?php echo esc_html( $job['type'] ); ?></div>
						</div>
						<div>
							<span class="ucm-pill ucm-pill-<?php echo esc_attr( $job['status'] ); ?>"><?php echo esc_html( $job['status'] ); ?></span>
							<div class="ucm-text-light"><?php echo esc_html( $job['age_label'] ); ?></div>
							<?php if ( $job['stage'] ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Stage: %s', 'universal-cpt-migrator' ), $job['stage'] ) ); ?></div>
							<?php endif; ?>
							<?php if ( $job['failure_category'] ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Category: %s', 'universal-cpt-migrator' ), $job['failure_category'] ) ); ?></div>
							<?php endif; ?>
							<?php if ( $job['failure_subsystem'] ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Subsystem: %s', 'universal-cpt-migrator' ), $job['failure_subsystem'] ) ); ?></div>
							<?php endif; ?>
						</div>
						<div>
							<div><?php echo esc_html( sprintf( __( 'Progress: %d%%', 'universal-cpt-migrator' ), (int) $job['progress'] ) ); ?></div>
							<?php if ( $job['failed_items'] ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Failed items: %d', 'universal-cpt-migrator' ), (int) $job['failed_items'] ) ); ?></div>
							<?php endif; ?>
							<?php if ( $job['warning_items'] ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Warnings: %d', 'universal-cpt-migrator' ), (int) $job['warning_items'] ) ); ?></div>
							<?php endif; ?>
							<?php if ( $job['log_path'] ) : ?>
								<div class="ucm-text-light"><?php echo esc_html( $job['log_path'] ); ?></div>
							<?php endif; ?>
						</div>
						<div>
							<div><?php echo esc_html( $job['recommended_action'] ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
