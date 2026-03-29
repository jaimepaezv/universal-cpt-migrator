<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap ucm-wrap" data-ucm-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-ucm-nonce="<?php echo esc_attr( wp_create_nonce( 'ucm_admin_nonce' ) ); ?>">
	<?php $current_page = 'export'; include UCM_PATH . 'templates/partials/navigation.php'; ?>

	<section class="ucm-page-hero ucm-page-hero-export">
		<div class="ucm-page-hero__content">
			<span class="ucm-eyebrow">Export Items</span>
			<div class="ucm-header">
				<div>
					<h1>Package Builder</h1>
					<p class="ucm-page-intro">Build a migration-grade bundle with schema, records, UUID mappings, and packaged media so destination sites can validate and import without relying on local IDs or remote fetches.</p>
				</div>
			</div>
		</div>
		<div class="ucm-page-hero__aside">
			<div class="ucm-hero-panel">
				<strong>Direct ZIP export</strong>
				<p>This screen is optimized for immediate package generation and download so you can verify real bundles quickly during manual testing.</p>
				<ul class="ucm-mini-list">
					<li>Portable schema + content in one package.</li>
					<li>Bundled media transport included.</li>
					<li>Immediate secured ZIP delivery on completion.</li>
				</ul>
			</div>
		</div>
	</section>

	<div class="ucm-summary-grid">
		<div class="ucm-summary-card"><strong>Schema + Content</strong><span>Portable Package</span></div>
		<div class="ucm-summary-card"><strong>Direct Download</strong><span>Single Request</span></div>
		<div class="ucm-summary-card"><strong>Media Bundles</strong><span>Local Binary Transport</span></div>
	</div>

	<div class="ucm-page-grid">
		<div class="ucm-main-column">
			<div class="ucm-card ucm-workflow-card">
				<div class="ucm-card-heading">
					<div>
						<h2>Build Export Package</h2>
						<p class="ucm-text-light">Select the target content type, inspect its profile, then generate and download the package immediately.</p>
					</div>
					<div class="ucm-card-heading__meta">
						<span class="ucm-kicker">Direct Export</span>
					</div>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ucm-export-form">
					<input type="hidden" name="action" value="ucm_export_now">
					<?php wp_nonce_field( 'ucm_export_now' ); ?>
					<div class="ucm-form-row">
						<label class="ucm-label" for="ucm-export-post-type">Content type</label>
						<select id="ucm-export-post-type" name="post_type" class="ucm-input" aria-describedby="ucm-export-help">
							<option value="">Loading available types...</option>
						</select>
						<p id="ucm-export-help" class="ucm-text-light">Choose the content type you want to export. Clicking the button downloads the ZIP immediately from this request, without queueing a background job.</p>
					</div>
					<div class="ucm-actions">
						<button class="ucm-btn" id="ucm-export-run" type="submit">Export ZIP Now</button>
					</div>
				</form>
				<div id="ucm-export-type-profile" class="ucm-context-panel">
					<strong>Waiting for a content type selection.</strong>
					<p class="ucm-text-light">Choose a content type to inspect its visibility, REST support, taxonomies, and supported features before exporting.</p>
				</div>
				<hr class="ucm-divider">
				<div id="ucm-export-status" class="ucm-status-panel" role="status" aria-live="polite">
					<div class="ucm-status-shell is-idle">
						<div class="ucm-status-shell__header">
							<div>
								<strong>Export status</strong>
								<p class="ucm-text-light">This screen runs a direct export. Select a content type and click the button to generate and download the ZIP in the same request.</p>
							</div>
							<span class="ucm-pill ucm-pill-queued">Idle</span>
						</div>
						<div class="ucm-summary-grid ucm-summary-grid-compact">
							<div class="ucm-summary-card"><strong>Ready</strong><span>Current State</span></div>
							<div class="ucm-summary-card"><strong>Direct</strong><span>Mode</span></div>
							<div class="ucm-summary-card"><strong>Generate and download</strong><span>Next Step</span></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<aside class="ucm-sidebar-column">
			<div class="ucm-card ucm-side-card">
				<h3>Before You Export</h3>
				<ul class="ucm-list">
					<li>Confirm the content type has the records and media you expect.</li>
					<li>Use this direct mode for quick validation and manual QA of real ZIP packages.</li>
					<li>If you need long-running job triage, use Diagnostics for the plugin’s background flows elsewhere.</li>
				</ul>
			</div>
			<div class="ucm-card ucm-side-card">
				<h3>Operator Links</h3>
				<div class="ucm-quick-links ucm-quick-links-vertical">
					<a class="ucm-btn ucm-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-diagnostics' ) ); ?>">Open Diagnostics</a>
					<a class="ucm-btn ucm-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-logs' ) ); ?>">Open Logs</a>
					<a class="ucm-btn ucm-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-settings' ) ); ?>">Review Settings</a>
				</div>
			</div>
		</aside>
	</div>
</div>
