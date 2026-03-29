<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap ucm-wrap" data-ucm-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-ucm-nonce="<?php echo esc_attr( wp_create_nonce( 'ucm_admin_nonce' ) ); ?>">
	<?php $current_page = 'import'; include UCM_PATH . 'templates/partials/navigation.php'; ?>

	<section class="ucm-page-hero ucm-page-hero-import">
		<div class="ucm-page-hero__content">
			<span class="ucm-eyebrow">Import Packages</span>
			<div class="ucm-header">
				<div>
					<h1>Package Importer</h1>
					<p class="ucm-page-intro">Validate first, then import safely. The importer remaps UUID relationships, resolves taxonomies and media, and keeps resumable background state for large jobs.</p>
				</div>
			</div>
		</div>
		<div class="ucm-page-hero__aside">
			<div class="ucm-hero-panel">
				<strong>Safer import path</strong>
				<p>Dry runs expose compatibility and portability issues before the worker writes anything to the database.</p>
				<ul class="ucm-mini-list">
					<li>UUID-based remapping for relationships.</li>
					<li>Packaged media and policy validation.</li>
					<li>Resumable import jobs for large datasets.</li>
				</ul>
			</div>
		</div>
	</section>

	<div class="ucm-summary-grid">
		<div class="ucm-summary-card"><strong>Dry Run First</strong><span>Compatibility Gate</span></div>
		<div class="ucm-summary-card"><strong>Relationship Safe</strong><span>UUID Remapping</span></div>
		<div class="ucm-summary-card"><strong>Resumable</strong><span>Background Import Jobs</span></div>
	</div>

	<div class="ucm-page-grid">
		<div class="ucm-main-column">
			<div class="ucm-card ucm-workflow-card">
				<div class="ucm-card-heading">
					<div>
						<h2>Import Workflow</h2>
						<p class="ucm-text-light">Upload a package, inspect its profile, validate it, then run or resume the import.</p>
					</div>
					<div class="ucm-card-heading__meta">
						<span class="ucm-kicker">Guided Import</span>
					</div>
				</div>
				<div class="ucm-form-row">
					<label class="ucm-label" for="ucm-package-upload">Import package</label>
					<input type="file" id="ucm-package-upload" accept=".json,.zip" class="ucm-input ucm-file-input" aria-describedby="ucm-import-help">
					<p id="ucm-import-help" class="ucm-text-light">ZIP packages preserve bundled media and portable manifests. JSON packages are valid for schema and content-only workflows.</p>
				</div>
				<div id="ucm-package-profile" class="ucm-context-panel">
					<strong>No package selected yet.</strong>
					<p class="ucm-text-light">Choose a JSON or ZIP package to review its filename, type, and size before running validation.</p>
				</div>
				<div class="ucm-actions">
					<button class="ucm-btn" id="ucm-validate-import">Validate Package (Dry Run)</button>
					<button class="ucm-btn ucm-btn-secondary" id="ucm-run-import">Run Full Import</button>
					<button class="ucm-btn ucm-btn-ghost" id="ucm-resume-import" data-job-id="<?php echo isset( $_GET['ucm_job_id'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['ucm_job_id'] ) ) ) : ''; ?>" <?php disabled( ! isset( $_GET['ucm_job_id'] ) || '' === sanitize_text_field( wp_unslash( $_GET['ucm_job_id'] ) ) ); ?>>Resume Last Job</button>
				</div>
			</div>

			<div class="ucm-card ucm-workflow-card">
				<div class="ucm-card-heading">
					<div>
						<h2>Validation and Results</h2>
						<p class="ucm-text-light">Validation errors block import. Warnings indicate portability or compatibility concerns that should be reviewed before retrying or proceeding.</p>
					</div>
				</div>
				<div class="ucm-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Import progress"><span id="ucm-import-progress-bar"></span></div>
				<div id="ucm-import-results" class="ucm-status-panel" role="status" aria-live="polite">
					<p class="ucm-text-light">Waiting for a package.</p>
				</div>
			</div>
		</div>
		<aside class="ucm-sidebar-column">
			<div class="ucm-card ucm-side-card">
				<h3>Operator Checklist</h3>
				<ul class="ucm-list">
					<li>Run a dry run first whenever the target schema, taxonomies, or media settings have changed.</li>
					<li>Large jobs continue in the background. You can leave this page and return with the resumable job link.</li>
					<li>Use Diagnostics if progress stalls or if a job remains queued longer than expected.</li>
				</ul>
			</div>
			<div class="ucm-card ucm-side-card">
				<h3>Recovery Links</h3>
				<div class="ucm-quick-links ucm-quick-links-vertical">
					<a class="ucm-btn ucm-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-diagnostics' ) ); ?>">Open Diagnostics</a>
					<a class="ucm-btn ucm-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-logs' ) ); ?>">Open Logs</a>
					<a class="ucm-btn ucm-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-settings' ) ); ?>">Review Settings</a>
				</div>
			</div>
		</aside>
	</div>
</div>
