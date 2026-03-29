<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap ucm-wrap">
	<?php $current_page = 'logs'; include UCM_PATH . 'templates/partials/navigation.php'; ?>

	<section class="ucm-page-hero ucm-page-hero-logs">
		<div class="ucm-page-hero__content">
			<span class="ucm-eyebrow"><?php esc_html_e( 'Logs', 'universal-cpt-migrator' ); ?></span>
			<div class="ucm-header">
				<div>
					<h1><?php esc_html_e( 'Logs & Traces', 'universal-cpt-migrator' ); ?></h1>
					<p class="ucm-page-intro"><?php esc_html_e( 'Review stored job traces, filter by severity, and follow diagnostics-linked logs without losing operator context.', 'universal-cpt-migrator' ); ?></p>
				</div>
			</div>
		</div>
		<div class="ucm-page-hero__aside">
			<div class="ucm-hero-panel">
				<strong><?php esc_html_e( 'Forensic review', 'universal-cpt-migrator' ); ?></strong>
				<p><?php esc_html_e( 'Use this screen to confirm failure stage, subsystem, remediation cues, and warning density before retrying a migration.', 'universal-cpt-migrator' ); ?></p>
			</div>
		</div>
	</section>

	<div class="ucm-summary-grid">
		<div class="ucm-summary-card"><strong><?php echo esc_html( count( $logs ) ); ?></strong><span><?php esc_html_e( 'Available Logs', 'universal-cpt-migrator' ); ?></span></div>
		<div class="ucm-summary-card"><strong><?php echo esc_html( $log ? $log : __( 'None', 'universal-cpt-migrator' ) ); ?></strong><span><?php esc_html_e( 'Selected Log', 'universal-cpt-migrator' ); ?></span></div>
		<div class="ucm-summary-card"><strong><?php echo esc_html( $selected_entry ? size_format( (int) $selected_entry['size'] ) : __( '0 B', 'universal-cpt-migrator' ) ); ?></strong><span><?php esc_html_e( 'Log Size', 'universal-cpt-migrator' ); ?></span></div>
		<div class="ucm-summary-card"><strong><?php echo esc_html( (int) $level_counts['ERROR'] ); ?></strong><span><?php esc_html_e( 'Errors', 'universal-cpt-migrator' ); ?></span></div>
		<div class="ucm-summary-card"><strong><?php echo esc_html( (int) $level_counts['WARNING'] ); ?></strong><span><?php esc_html_e( 'Warnings', 'universal-cpt-migrator' ); ?></span></div>
		<div class="ucm-summary-card"><strong><?php echo esc_html( (int) $level_counts['INFO'] ); ?></strong><span><?php esc_html_e( 'Info Entries', 'universal-cpt-migrator' ); ?></span></div>
	</div>

	<div class="ucm-grid ucm-grid-logs">
		<div class="ucm-card ucm-workflow-card">
			<h3><?php esc_html_e( 'Available Logs', 'universal-cpt-migrator' ); ?></h3>
			<div class="ucm-context-panel ucm-logs-list-toolbar">
				<div class="ucm-form-row">
					<label class="ucm-label" for="ucm-log-search"><?php esc_html_e( 'Find log files', 'universal-cpt-migrator' ); ?></label>
					<input type="search" id="ucm-log-search" value="" class="ucm-input" placeholder="<?php esc_attr_e( 'Filter the log file list by filename', 'universal-cpt-migrator' ); ?>" aria-controls="ucm-log-list">
					<p class="ucm-text-light"><?php esc_html_e( 'This search only filters the file list on this screen. It does not change the trace preview.', 'universal-cpt-migrator' ); ?></p>
				</div>
			</div>
			<?php if ( empty( $logs ) ) : ?>
				<p class="ucm-text-light"><?php esc_html_e( 'No logs found.', 'universal-cpt-migrator' ); ?></p>
			<?php else : ?>
				<div class="ucm-result-list" id="ucm-log-list">
					<?php foreach ( $logs as $entry ) : ?>
						<a class="ucm-log-link" href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-logs&log=' . rawurlencode( $entry['name'] ) ) ); ?>">
							<strong><?php echo esc_html( $entry['name'] ); ?></strong>
							<span><?php echo esc_html( size_format( $entry['size'] ) ); ?></span>
							<span><?php echo esc_html( gmdate( 'Y-m-d H:i:s', $entry['modified'] ) ); ?> UTC</span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<div class="ucm-card ucm-workflow-card">
			<h3><?php esc_html_e( 'Log Preview', 'universal-cpt-migrator' ); ?></h3>
			<p class="ucm-text-light"><?php esc_html_e( 'Use this preview to confirm failure stage, item warnings, and the recommended remediation path before retrying a migration.', 'universal-cpt-migrator' ); ?></p>
			<form method="get" class="ucm-context-panel ucm-trace-filter-form">
				<input type="hidden" name="page" value="u-cpt-migrator-logs">
				<?php if ( $log ) : ?><input type="hidden" name="log" value="<?php echo esc_attr( $log ); ?>"><?php endif; ?>
				<?php if ( $job_id ) : ?><input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>"><?php endif; ?>
				<?php if ( $trace_subsystem ) : ?><input type="hidden" name="trace_subsystem" value="<?php echo esc_attr( $trace_subsystem ); ?>"><?php endif; ?>
				<div class="ucm-card-heading">
					<div>
						<h4><?php esc_html_e( 'Trace filters', 'universal-cpt-migrator' ); ?></h4>
						<p class="ucm-text-light"><?php esc_html_e( 'Apply these filters to the selected log preview only. They do not change the list of files on the left.', 'universal-cpt-migrator' ); ?></p>
					</div>
				</div>
				<div class="ucm-grid ucm-grid-trace-filters">
					<div class="ucm-form-row">
						<label class="ucm-label" for="ucm-trace-search"><?php esc_html_e( 'Search trace lines', 'universal-cpt-migrator' ); ?></label>
						<input type="search" id="ucm-trace-search" name="trace_search" value="<?php echo esc_attr( $trace_search ); ?>" class="ucm-input" placeholder="<?php esc_attr_e( 'Search by error code, message, UUID, or keyword', 'universal-cpt-migrator' ); ?>">
					</div>
					<div class="ucm-form-row">
						<label class="ucm-label" for="ucm-log-level"><?php esc_html_e( 'Entry level', 'universal-cpt-migrator' ); ?></label>
						<select id="ucm-log-level" name="level" class="ucm-input">
							<option value="ALL" <?php selected( 'ALL', $level ); ?>><?php esc_html_e( 'All entries', 'universal-cpt-migrator' ); ?></option>
							<option value="ERROR" <?php selected( 'ERROR', $level ); ?>><?php esc_html_e( 'Errors', 'universal-cpt-migrator' ); ?></option>
							<option value="WARNING" <?php selected( 'WARNING', $level ); ?>><?php esc_html_e( 'Warnings', 'universal-cpt-migrator' ); ?></option>
							<option value="INFO" <?php selected( 'INFO', $level ); ?>><?php esc_html_e( 'Info', 'universal-cpt-migrator' ); ?></option>
						</select>
					</div>
					<div class="ucm-actions ucm-actions-end">
						<button type="submit" class="ucm-btn ucm-btn-secondary"><?php esc_html_e( 'Apply Trace Filters', 'universal-cpt-migrator' ); ?></button>
					</div>
				</div>
			</form>
			<?php if ( $job_id ) : ?>
				<div class="ucm-context-panel">
					<div class="ucm-context-panel__header">
						<strong><?php echo esc_html( sprintf( __( 'Trace view for job %s', 'universal-cpt-migrator' ), $job_id ) ); ?></strong>
						<span class="ucm-pill ucm-pill-running"><?php esc_html_e( 'Job linked', 'universal-cpt-migrator' ); ?></span>
					</div>
					<p class="ucm-text-light"><?php esc_html_e( 'This preview was opened from diagnostics or recovery flow. Use the active filters to narrow the trace to the subsystem or failure keyword you are investigating.', 'universal-cpt-migrator' ); ?></p>
					<?php if ( $trace_subsystem ) : ?>
						<p class="ucm-text-light"><?php echo esc_html( sprintf( __( 'Related subsystem: %s', 'universal-cpt-migrator' ), $trace_subsystem ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<pre class="ucm-schema-viewer" tabindex="0"><?php echo esc_html( $preview ? $preview : ( $view ? __( 'No log entries matched the current filters.', 'universal-cpt-migrator' ) : __( 'Select a log to preview it here.', 'universal-cpt-migrator' ) ) ); ?></pre>
		</div>
	</div>
</div>
