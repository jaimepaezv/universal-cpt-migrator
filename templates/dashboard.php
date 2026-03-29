<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap ucm-wrap" id="ucm-app-container" data-ucm-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-ucm-nonce="<?php echo esc_attr( wp_create_nonce( 'ucm_admin_nonce' ) ); ?>">
	<?php $current_page = 'dashboard'; include UCM_PATH . 'templates/partials/navigation.php'; ?>

	<section class="ucm-page-hero ucm-page-hero-dashboard">
		<div class="ucm-page-hero__content">
			<span class="ucm-eyebrow">Discovery Workspace</span>
			<div class="ucm-header">
				<div>
					<h1>Universal CPT Migrator</h1>
					<p class="ucm-page-intro">Audit the current site model, inspect portability signals, and move directly into export, import, or diagnostics without hopping between disconnected admin utilities.</p>
				</div>
				<div class="ucm-badge">v<?php echo esc_html( UCM_VERSION ); ?></div>
			</div>
			<div class="ucm-inline-links">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-export' ) ); ?>" class="ucm-btn">Open Export Items</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-import' ) ); ?>" class="ucm-btn ucm-btn-secondary">Open Import Packages</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=u-cpt-migrator-diagnostics' ) ); ?>" class="ucm-btn ucm-btn-ghost">Open Diagnostics</a>
			</div>
		</div>
		<div class="ucm-page-hero__aside">
			<div class="ucm-hero-panel">
				<strong>Discovery Engine</strong>
				<p>Builds a live inventory of every content type that is useful to operators: public types, admin-only models, supports, taxonomies, REST exposure, and hierarchy.</p>
				<ul class="ucm-mini-list">
					<li>Use this screen before exporting production data.</li>
					<li>Generate sample packages from empty or lightly populated models.</li>
					<li>Spot portability issues before import time.</li>
				</ul>
			</div>
		</div>
	</section>

	<div class="ucm-summary-grid" id="ucm-discovery-summary">
		<div class="ucm-summary-card"><strong>0</strong><span>Discovered Types</span></div>
		<div class="ucm-summary-card"><strong>0</strong><span>Public</span></div>
		<div class="ucm-summary-card"><strong>0</strong><span>Admin Only</span></div>
		<div class="ucm-summary-card"><strong>0</strong><span>REST Ready</span></div>
	</div>

	<div class="ucm-card ucm-toolbar-card">
		<div class="ucm-card-heading">
			<div>
				<h2>Content Type Inventory</h2>
				<p class="ucm-text-light">Filter by visibility or search across labels, slugs, supports, and taxonomies to focus the discovery panel on the models you actually need to migrate.</p>
			</div>
			<div class="ucm-card-heading__meta">
				<span class="ucm-kicker">Live Site Model</span>
			</div>
		</div>
		<div class="ucm-dashboard-toolbar">
			<div class="ucm-form-row ucm-form-row--grow">
				<label class="ucm-label" for="ucm-cpt-search">Search content types</label>
				<input type="search" id="ucm-cpt-search" class="ucm-input" placeholder="Search by label, slug, taxonomy, or support" />
			</div>
			<div class="ucm-form-row ucm-form-row--compact">
				<label class="ucm-label" for="ucm-cpt-visibility-filter">Visibility</label>
				<select id="ucm-cpt-visibility-filter" class="ucm-input">
					<option value="all">All visible types</option>
					<option value="public">Public</option>
					<option value="admin-only">Admin only</option>
				</select>
			</div>
		</div>
		<div class="ucm-note-strip">
			<span>Exclude internal types with <code>u_cpt_mgr_exclude_types</code>.</span>
			<span>Opt in built-in types with <code>u_cpt_mgr_include_builtin_types</code>.</span>
		</div>
	</div>

	<div id="ucm-dashboard">
		<div class="ucm-empty-state">
			<div class="ucm-loader"></div>
			<p>Initializing Discovery Service...</p>
		</div>
	</div>
</div>
