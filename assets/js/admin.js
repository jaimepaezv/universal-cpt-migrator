(function($) {
    'use strict';

    const UCM = {
        init: function() {
            this.cptData = [];
            this.cacheDOM();
            this.bindEvents();
            if ($('#ucm-dashboard').length || $('#ucm-export-post-type').length) {
                this.loadCPTs();
            }
            this.hydrateImportJobFromQuery();
        },

        cacheDOM: function() {
            this.$container = $('#ucm-app-container');
            this.$loader = $('.ucm-global-loader');
        },

        bindEvents: function() {
            $(document).on('click', '.ucm-analyze-btn', this.analyzeSchema.bind(this));
            $(document).on('click', '.ucm-sample-btn', this.generateSample.bind(this));
            $(document).on('click', '#ucm-validate-import', (e) => this.handleImport(e, 'ucm_validate_import'));
            $(document).on('click', '#ucm-run-import', (e) => this.handleImport(e, 'ucm_run_import'));
            $(document).on('click', '#ucm-resume-import', this.resumeImport.bind(this));
            $(document).on('input', '#ucm-cpt-search', this.applyCPTFilters.bind(this));
            $(document).on('change', '#ucm-cpt-visibility-filter', this.applyCPTFilters.bind(this));
            $(document).on('change', '#ucm-export-post-type', this.updateSelectedExportType.bind(this));
            $(document).on('change', '#ucm-package-upload', this.updatePackageProfile.bind(this));
            $(document).on('input', '#ucm-log-search', this.filterLogs.bind(this));
        },

        hydrateImportJobFromQuery: function() {
            const $resumeButton = $('#ucm-resume-import');
            if (!$resumeButton.length) {
                return;
            }

            const jobId = $resumeButton.data('job-id');
            if (!jobId) {
                return;
            }

            this.renderMessage('#ucm-import-results', 'Loading the selected import job...', 'info');
            this.pollJobStatus(jobId, 'import', '#ucm-import-results', $resumeButton, true);
        },

        loadCPTs: function() {
            this.showLoader();
            $.ajax({
                url: ucmLocal.ajaxurl,
                method: 'POST',
                data: { action: 'ucm_get_cpts', nonce: ucmLocal.nonce },
                success: (response) => {
                    this.hideLoader();
                    if (response.success) {
                        this.cptData = Array.isArray(response.data) ? response.data : [];
                        this.updateDiscoverySummary(this.cptData);
                        this.renderCPTList(this.cptData);
                        this.populatePostTypeSelect(this.cptData);
                        this.updateSelectedExportType();
                    }
                }
            });
        },

        updateSelectedExportType: function() {
            const $profile = $('#ucm-export-type-profile');
            if (!$profile.length) {
                return;
            }

            const slug = $('#ucm-export-post-type').val();
            const selected = this.cptData.find((cpt) => cpt.slug === slug);

            if (!selected) {
                $profile.html(`
                    <strong>Waiting for a content type selection.</strong>
                    <p class="ucm-text-light">Choose a content type to inspect its visibility, REST support, taxonomies, and supported features before exporting.</p>
                `);
                return;
            }

            const supports = Array.isArray(selected.supports) && selected.supports.length
                ? selected.supports.map((support) => `<span class="ucm-badge">${this.escapeHtml(support)}</span>`).join('')
                : '<span class="ucm-text-light">No explicit supports registered.</span>';
            const taxonomies = Array.isArray(selected.taxonomies) && selected.taxonomies.length
                ? selected.taxonomies.map((taxonomy) => `<span class="ucm-badge">${this.escapeHtml(taxonomy)}</span>`).join('')
                : '<span class="ucm-text-light">No taxonomies attached.</span>';

            $profile.html(`
                <div class="ucm-context-panel__header">
                    <strong>${this.escapeHtml(selected.label)}</strong>
                    <span class="ucm-pill ${selected.visibility === 'admin-only' ? 'ucm-pill-running' : 'ucm-pill-completed'}">${this.escapeHtml(selected.visibility || 'public')}</span>
                </div>
                <p class="ucm-text-light">${this.escapeHtml(selected.description || selected.singular_label || 'No description available for this content type.')}</p>
                <div class="ucm-panel-meta">
                    <span>Key: <code>${this.escapeHtml(selected.slug)}</code></span>
                    <span>Records: <strong>${this.escapeHtml(String(selected.count))}</strong></span>
                    <span>REST: <strong>${selected.show_in_rest ? 'Yes' : 'No'}</strong></span>
                    <span>Hierarchy: <strong>${selected.hierarchical ? 'Yes' : 'No'}</strong></span>
                </div>
                <div class="ucm-context-panel__section">
                    <strong>Supports</strong>
                    <div class="ucm-inline-badges">${supports}</div>
                </div>
                <div class="ucm-context-panel__section">
                    <strong>Taxonomies</strong>
                    <div class="ucm-inline-badges">${taxonomies}</div>
                </div>
            `);
        },

        updatePackageProfile: function() {
            const $profile = $('#ucm-package-profile');
            if (!$profile.length) {
                return;
            }

            const input = document.getElementById('ucm-package-upload');
            const file = input && input.files ? input.files[0] : null;

            if (!file) {
                $profile.html(`
                    <strong>No package selected yet.</strong>
                    <p class="ucm-text-light">Choose a JSON or ZIP package to review its filename, type, and size before running validation.</p>
                `);
                return;
            }

            const extension = String(file.name).split('.').pop().toLowerCase();
            const packageType = extension === 'zip' ? 'Bundled ZIP package' : 'JSON package';
            $profile.html(`
                <div class="ucm-context-panel__header">
                    <strong>${this.escapeHtml(file.name)}</strong>
                    <span class="ucm-pill ${extension === 'zip' ? 'ucm-pill-completed' : 'ucm-pill-running'}">${this.escapeHtml(packageType)}</span>
                </div>
                <div class="ucm-panel-meta">
                    <span>Size: <strong>${this.escapeHtml(this.formatBytes(file.size))}</strong></span>
                    <span>Extension: <code>.${this.escapeHtml(extension)}</code></span>
                </div>
                <p class="ucm-text-light">Run a dry run first to verify compatibility warnings, media policy, and relationship portability before writing data.</p>
            `);
        },

        filterLogs: function() {
            const query = String($('#ucm-log-search').val() || '').toLowerCase().trim();
            $('#ucm-log-list .ucm-log-link').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(!query || text.indexOf(query) !== -1);
            });
        },

        applyCPTFilters: function() {
            const search = String($('#ucm-cpt-search').val() || '').toLowerCase().trim();
            const visibility = String($('#ucm-cpt-visibility-filter').val() || 'all');
            const filtered = this.cptData.filter((cpt) => {
                const matchesVisibility = visibility === 'all' || String(cpt.visibility) === visibility;
                if (!matchesVisibility) {
                    return false;
                }

                if (!search) {
                    return true;
                }

                const haystack = [
                    cpt.slug,
                    cpt.label,
                    cpt.singular_label,
                    cpt.description,
                    ...(Array.isArray(cpt.supports) ? cpt.supports : []),
                    ...(Array.isArray(cpt.taxonomies) ? cpt.taxonomies : [])
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                return haystack.indexOf(search) !== -1;
            });

            this.renderCPTList(filtered);
        },

        populatePostTypeSelect: function(cpts) {
            const $select = $('#ucm-export-post-type');
            if (!$select.length) {
                return;
            }

            let options = '<option value="">Select a content type</option>';
            cpts.forEach((cpt) => {
                options += `<option value="${this.escapeHtml(cpt.slug)}">${this.escapeHtml(cpt.label)} (${this.escapeHtml(String(cpt.count))})</option>`;
            });

            $select.html(options);
        },

        updateDiscoverySummary: function(cpts) {
            const $summary = $('#ucm-discovery-summary');
            if (!$summary.length) {
                return;
            }

            const total = cpts.length;
            const publicTypes = cpts.filter((cpt) => Boolean(cpt.public)).length;
            const adminOnly = cpts.filter((cpt) => String(cpt.visibility) === 'admin-only').length;
            const restReady = cpts.filter((cpt) => Boolean(cpt.show_in_rest)).length;

            $summary.html(`
                <div class="ucm-summary-card"><strong>${this.escapeHtml(String(total))}</strong><span>Discovered Types</span></div>
                <div class="ucm-summary-card"><strong>${this.escapeHtml(String(publicTypes))}</strong><span>Public</span></div>
                <div class="ucm-summary-card"><strong>${this.escapeHtml(String(adminOnly))}</strong><span>Admin Only</span></div>
                <div class="ucm-summary-card"><strong>${this.escapeHtml(String(restReady))}</strong><span>REST Ready</span></div>
            `);
        },

        renderCPTList: function(cpts) {
            if (!cpts.length) {
                $('#ucm-dashboard').html(`
                    <div class="ucm-card ucm-workflow-card">
                        <strong>No content types matched the current filters.</strong>
                        <p class="ucm-text-light">Clear the search terms or switch the visibility filter to see the full discovery inventory.</p>
                    </div>
                `);
                return;
            }

            let html = '<div class="ucm-grid">';
            cpts.forEach(cpt => {
                const supports = Array.isArray(cpt.supports) && cpt.supports.length
                    ? cpt.supports.map((support) => `<span class="ucm-badge">${this.escapeHtml(support)}</span>`).join('')
                    : '<span class="ucm-text-light">No explicit supports registered.</span>';
                const taxonomies = Array.isArray(cpt.taxonomies) && cpt.taxonomies.length
                    ? cpt.taxonomies.map((taxonomy) => `<span class="ucm-badge">${this.escapeHtml(taxonomy)}</span>`).join('')
                    : '<span class="ucm-text-light">No taxonomies attached.</span>';
                const visibilityClass = cpt.visibility === 'admin-only' ? 'ucm-pill-running' : 'ucm-pill-completed';

                html += `
                    <div class="ucm-card ucm-cpt-card" data-slug="${this.escapeHtml(cpt.slug)}">
                        <div class="ucm-cpt-card__header">
                            <div>
                                <h4>${this.escapeHtml(cpt.label)}</h4>
                                <p class="ucm-text-light" style="margin:0">${this.escapeHtml(cpt.description || cpt.singular_label || 'No description provided for this type.')}</p>
                            </div>
                            <span class="ucm-pill ${visibilityClass}">${this.escapeHtml(cpt.visibility || 'public')}</span>
                        </div>
                        <div class="ucm-panel-meta" style="margin-bottom:15px">
                            <span>Key: <code>${this.escapeHtml(cpt.slug)}</code></span>
                            <span>Records: <strong>${this.escapeHtml(String(cpt.count))}</strong></span>
                            <span>REST: <strong>${cpt.show_in_rest ? 'Yes' : 'No'}</strong></span>
                            <span>Hierarchy: <strong>${cpt.hierarchical ? 'Yes' : 'No'}</strong></span>
                        </div>
                        <div class="ucm-cpt-card__section">
                            <strong>Supports</strong>
                            <div class="ucm-inline-badges">${supports}</div>
                        </div>
                        <div class="ucm-cpt-card__section">
                            <strong>Taxonomies</strong>
                            <div class="ucm-inline-badges">${taxonomies}</div>
                        </div>
                        <div class="ucm-actions">
                            <button class="ucm-btn ucm-analyze-btn" data-slug="${cpt.slug}">Analyze</button>
                            <button class="ucm-btn ucm-btn-secondary ucm-sample-btn" data-slug="${cpt.slug}">Get Sample</button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            $('#ucm-dashboard').html(html);
        },

        analyzeSchema: function(e) {
            const slug = $(e.target).data('slug');
            const $btn = $(e.target);
            $btn.html('<div class="ucm-loader"></div> Analyzing...');

            $.ajax({
                url: ucmLocal.ajaxurl,
                method: 'POST',
                data: { action: 'ucm_analyze_schema', post_type: slug, nonce: ucmLocal.nonce },
                success: (response) => {
                    $btn.html('Analyze');
                    if (response.success) {
                        this.renderSchemaModal(response.data);
                    }
                }
            });
        },

        generateSample: function(e) {
            const slug = $(e.target).data('slug');
            $.ajax({
                url: ucmLocal.ajaxurl,
                method: 'POST',
                data: { action: 'ucm_generate_sample', post_type: slug, nonce: ucmLocal.nonce },
                success: (response) => {
                    if (response.success) {
                        const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `sample-${slug}.json`;
                        a.click();
                    }
                }
            });
        },

        handleImport: function(e, action) {
            e.preventDefault();
            const fileInput = document.getElementById('ucm-package-upload');
            const file = fileInput && fileInput.files ? fileInput.files[0] : null;
            const $button = $(e.currentTarget);

            if (!file) {
                this.renderMessage('#ucm-import-results', 'Choose a JSON or ZIP package before continuing.', 'error');
                return;
            }

            this.setButtonState($button, true, action === 'ucm_validate_import' ? 'Validating...' : 'Importing...');
            this.renderMessage('#ucm-import-results', 'Processing uploaded package...', 'info');
            this.setProgress(0);
            if (action === 'ucm_run_import') {
                this.startBackgroundImport(file, action, $button);
                return;
            }

            this.processImportChunk(file, action, 0, { imported: 0, updated: 0, failed: 0, items: [] }, $button, '');
        },

        startBackgroundImport: function(file, action, $button) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', ucmLocal.nonce);
            formData.append('package', file);

            $.ajax({
                url: ucmLocal.ajaxurl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (!response.success) {
                        this.renderImportFailure(response);
                        this.setButtonState($button, false, 'Run Full Import');
                        return;
                    }

                    this.renderImportSuccess(response.data);
                    this.pollJobStatus(response.data.job_id, 'import', '#ucm-import-results', $button);
                },
                error: (xhr) => {
                    this.renderRequestError('#ucm-import-results', xhr);
                    this.setButtonState($button, false, 'Run Full Import');
                }
            });
        },

        processImportChunk: function(file, action, offset, aggregate, $button, jobId) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', ucmLocal.nonce);
            formData.append('package', file);
            formData.append('offset', offset);
            if (jobId) {
                formData.append('job_id', jobId);
            }

            $.ajax({
                url: ucmLocal.ajaxurl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (!response.success) {
                        this.renderImportFailure(response);
                        this.setButtonState($button, false, action === 'ucm_validate_import' ? 'Validate Package (Dry Run)' : 'Run Full Import');
                        return;
                    }

                    const results = response.data.results || {};
                    const job_id = response.data.job_id || jobId;
                    aggregate.imported += Number(results.imported || 0);
                    aggregate.updated += Number(results.updated || 0);
                    aggregate.failed += Number(results.failed || 0);
                    if (!jobId) {
                        aggregate.items = aggregate.items.concat(Array.isArray(results.items) ? results.items : []);
                    }

                    response.data.results = {
                        imported: aggregate.imported,
                        updated: aggregate.updated,
                        failed: aggregate.failed,
                        items: Array.isArray(results.items) ? results.items : aggregate.items,
                        offset: results.offset || 0,
                        next_offset: results.next_offset || 0,
                        has_more: results.has_more || false
                    };

                    this.setProgress(this.calculateProgress(response.data, results));
                    this.renderImportSuccess(response.data);

                    if (results.has_more) {
                        this.processImportChunk(file, action, results.next_offset, aggregate, $button, job_id);
                        return;
                    }

                    this.setProgress(100);
                    this.setButtonState($button, false, action === 'ucm_validate_import' ? 'Validate Package (Dry Run)' : 'Run Full Import');
                },
                error: (xhr) => {
                    const payload = xhr.responseJSON || {};
                    if (payload && payload.success === false) {
                        this.renderImportFailure(payload);
                    } else {
                        this.renderRequestError('#ucm-import-results', xhr);
                    }

                    this.setButtonState($button, false, action === 'ucm_validate_import' ? 'Validate Package (Dry Run)' : 'Run Full Import');
                }
            });
        },

        resumeImport: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const jobId = $button.data('job-id');

            if (!jobId) {
                this.renderMessage('#ucm-import-results', 'No resumable job was found for this screen.', 'error');
                return;
            }

            this.setButtonState($button, true, 'Resuming...');
            this.renderMessage('#ucm-import-results', 'Resuming import job...', 'info');

            this.pollJobStatus(jobId, 'import', '#ucm-import-results', $button, true);
        },

        pollJobStatus: function(jobId, jobType, selector, $button, immediate = false) {
            const pollState = {
                lastProgress: null,
                lastOffset: null,
                lastStage: '',
                stagnantPolls: 0
            };

            const run = () => {
                $.ajax({
                    url: ucmLocal.ajaxurl,
                    method: 'POST',
                    data: { action: 'ucm_get_job_status', nonce: ucmLocal.nonce, job_id: jobId },
                    success: (response) => {
                        if (!response.success) {
                            this.renderAjaxError(selector, response);
                            this.finishJobButton(jobType, $button);
                            return;
                        }

                        const state = response.data || {};
                        this.setProgress(Number(state.progress || 0));

                        if (state.status === 'queued' || state.status === 'running') {
                            const progress = Number(state.progress || 0);
                            const offset = Number(state.offset || 0);
                            const stage = String(state.stage || '');
                            const unchanged = pollState.lastProgress === progress
                                && pollState.lastOffset === offset
                                && pollState.lastStage === stage;

                            pollState.stagnantPolls = unchanged ? pollState.stagnantPolls + 1 : 0;
                            pollState.lastProgress = progress;
                            pollState.lastOffset = offset;
                            pollState.lastStage = stage;
                            state.stalled_hint = pollState.stagnantPolls >= 8;
                        } else {
                            pollState.stagnantPolls = 0;
                            pollState.lastProgress = null;
                            pollState.lastOffset = null;
                            pollState.lastStage = '';
                        }

                        if (jobType === 'export') {
                            this.renderExportJobState(state);
                        } else {
                            this.renderBackgroundImportState(state);
                        }

                        if (state.status === 'queued' || state.status === 'running') {
                            window.setTimeout(run, 1500);
                            return;
                        }

                        this.finishJobButton(jobType, $button);
                    },
                    error: (xhr) => {
                        this.renderRequestError(selector, xhr);
                        this.finishJobButton(jobType, $button);
                    }
                });
            };

            if (immediate) {
                run();
                return;
            }

            window.setTimeout(run, 1000);
        },

        renderExportJobState: function(state) {
            const status = String(state.status || 'queued');
            const stage = String(state.failed_stage || state.stage || '').trim();
            const progress = Math.max(0, Math.min(100, Number(state.progress || 0)));
            const stageLabel = stage ? this.humanizeSlug(stage) : (status === 'completed' ? 'Completed' : (status === 'failed' ? 'Failed' : 'Preparing'));
            const subsystem = state.failure_subsystem ? `<span>Subsystem: <code>${this.escapeHtml(state.failure_subsystem)}</code></span>` : '';
            const errorCode = state.error_code ? `<span>Error code: <code>${this.escapeHtml(state.error_code)}</code></span>` : '';
            const logLink = this.buildLogUrl(state.log_path || '');

            if (state.status === 'completed') {
                const zipFile = state.artifacts && state.artifacts.zip_file ? state.artifacts.zip_file : '';
                $('#ucm-export-status').html(`
                    <div class="ucm-status-shell is-complete">
                        <div class="ucm-status-shell__header">
                            <div>
                                <strong>Export complete</strong>
                                <p class="ucm-text-light">The package is ready. Download it now or go to Diagnostics if you need to inspect the completed job.</p>
                            </div>
                            <span class="ucm-pill ucm-pill-completed">Ready</span>
                        </div>
                        <div class="ucm-progress ucm-progress--export" aria-hidden="true"><span style="width:100%"></span></div>
                        <div class="ucm-summary-grid ucm-summary-grid-compact">
                            <div class="ucm-summary-card"><strong>100%</strong><span>Progress</span></div>
                            <div class="ucm-summary-card"><strong>${this.escapeHtml(state.job_id || 'Completed')}</strong><span>Job</span></div>
                            <div class="ucm-summary-card"><strong>${this.escapeHtml(String(state.package && state.package.items ? state.package.items.length : 0))}</strong><span>Items Packaged</span></div>
                        </div>
                        <div class="ucm-panel-meta">
                            <span>Stage: <code>${this.escapeHtml(stageLabel)}</code></span>
                            ${zipFile ? `<span>ZIP bundle: <code>${this.escapeHtml(zipFile)}</code></span>` : ''}
                            ${state.log_path ? `<span>Log: <code>${this.escapeHtml(state.log_path)}</code></span>` : ''}
                        </div>
                        <p class="ucm-text-light">Keep the ZIP until the destination site has validated and imported it.</p>
                        <div class="ucm-inline-links">
                            ${state.download_url ? `<a class="ucm-btn" href="${this.escapeHtml(state.download_url)}">Download Export ZIP</a>` : ''}
                            <a href="${this.escapeHtml(ucmLocal.urls.diagnostics)}">Open Diagnostics</a>
                            ${logLink ? `<a href="${this.escapeHtml(logLink)}">Open Log Preview</a>` : ''}
                            <a href="${this.escapeHtml(ucmLocal.urls.logs)}">Open Logs</a>
                        </div>
                    </div>
                `);
                return;
            }

            if (state.status === 'failed') {
                $('#ucm-export-status').html(`
                    <div class="ucm-status-shell is-failed">
                        <div class="ucm-status-shell__header">
                            <div>
                                <strong>Export failed</strong>
                                <p class="ucm-text-light">${this.escapeHtml(state.error || 'The export worker did not complete successfully.')}</p>
                            </div>
                            <span class="ucm-pill ucm-pill-failed">Failed</span>
                        </div>
                        <div class="ucm-progress ucm-progress--export" aria-hidden="true"><span style="width:${this.escapeHtml(String(progress))}%"></span></div>
                        <div class="ucm-summary-grid ucm-summary-grid-compact">
                            <div class="ucm-summary-card"><strong>${this.escapeHtml(String(progress))}%</strong><span>Progress</span></div>
                            <div class="ucm-summary-card"><strong>${this.escapeHtml(state.job_id || 'Unknown')}</strong><span>Job</span></div>
                            <div class="ucm-summary-card"><strong>${this.escapeHtml(stageLabel)}</strong><span>Failure Stage</span></div>
                        </div>
                        <div class="ucm-panel-meta">
                            <span>Stage: <code>${this.escapeHtml(stageLabel)}</code></span>
                            ${errorCode}
                            ${subsystem}
                            ${state.log_path ? `<span>Log: <code>${this.escapeHtml(state.log_path)}</code></span>` : ''}
                        </div>
                        <p class="ucm-text-light">Review the log and diagnostics before queuing another export. The button above is available again once the failure state is loaded.</p>
                        <div class="ucm-inline-links">
                            <span><a href="${this.escapeHtml(ucmLocal.urls.diagnostics)}">Open Diagnostics</a></span>
                            ${logLink ? `<span><a href="${this.escapeHtml(logLink)}">Open Log Preview</a></span>` : ''}
                            <span><a href="${this.escapeHtml(ucmLocal.urls.logs)}">Open Logs</a></span>
                        </div>
                    </div>
                `);
                return;
            }

            $('#ucm-export-status').html(`
                <div class="ucm-status-shell is-active">
                    <div class="ucm-status-shell__header">
                        <div>
                            <strong>${status === 'running' ? 'Export in progress' : 'Export queued'}</strong>
                            <p class="ucm-text-light">${status === 'running' ? 'The package is currently being assembled in the background. You can stay here for updates or leave and return later.' : 'The job has been accepted and is waiting for the background worker to start processing.'}</p>
                        </div>
                        <span class="ucm-pill ${status === 'running' ? 'ucm-pill-running' : 'ucm-pill-queued'}">${this.escapeHtml(this.humanizeSlug(status))}</span>
                    </div>
                    <div class="ucm-progress ucm-progress--export" aria-hidden="true"><span style="width:${this.escapeHtml(String(progress))}%"></span></div>
                    <div class="ucm-summary-grid ucm-summary-grid-compact">
                        <div class="ucm-summary-card"><strong>${this.escapeHtml(String(progress))}%</strong><span>Progress</span></div>
                        <div class="ucm-summary-card"><strong>${this.escapeHtml(state.job_id || '')}</strong><span>Job ID</span></div>
                        <div class="ucm-summary-card"><strong>${this.escapeHtml(stageLabel)}</strong><span>Current Stage</span></div>
                    </div>
                    <div class="ucm-panel-meta">
                        ${state.log_path ? `<span>Log: <code>${this.escapeHtml(state.log_path)}</code></span>` : ''}
                        <span>Next step: <strong>${status === 'running' ? 'Wait for completion or open Diagnostics' : 'Wait for worker start'}</strong></span>
                    </div>
                    <div class="ucm-inline-links">
                        <a href="${this.escapeHtml(ucmLocal.urls.diagnostics)}">Open Diagnostics</a>
                        ${logLink ? `<a href="${this.escapeHtml(logLink)}">Open Log Preview</a>` : ''}
                        <a href="${this.escapeHtml(ucmLocal.urls.logs)}">Open Logs</a>
                    </div>
                </div>
            `);
        },

        renderBackgroundImportState: function(state) {
            const payload = {
                status: state.status || '',
                mode: state.mode || 'import',
                job_id: state.job_id || '',
                resume_url: state.resume_url || '',
                log_path: state.log_path || '',
                validation: state.validation || {},
                results: Object.assign({
                    imported: 0,
                    updated: 0,
                    failed: 0,
                    items: [],
                    offset: state.offset || 0,
                    next_offset: state.offset || 0,
                    has_more: state.status === 'queued' || state.status === 'running'
                }, state.results || {})
            };

            if (state.status === 'failed') {
                this.renderImportFailure({
                    data: {
                        message: state.error || 'Import failed.',
                        log_path: state.log_path || '',
                        validation: state.validation || {},
                        results: state.results || {},
                        job_id: state.job_id || '',
                        resume_url: state.resume_url || '',
                        stage: state.stage || '',
                        failed_stage: state.failed_stage || '',
                        error_code: state.error_code || '',
                        failure_subsystem: state.failure_subsystem || ''
                    }
                });
                return;
            }

            this.renderImportSuccess(payload);
        },

        finishJobButton: function(jobType, $button) {
            this.setButtonState($button, false, jobType === 'export' ? 'Export ZIP Now' : 'Run Full Import');
        },

        renderSchemaModal: function(schema) {
            // Simplified for artifact - In production, this would open a side panel or modal
            const pretty = JSON.stringify(schema, null, 2);
            const overlay = $(`<div id="ucm-overlay" class="ucm-overlay">
                <div class="ucm-modal-card">
                    <button id="ucm-close-modal" class="ucm-btn ucm-btn-ghost ucm-modal-close">Close</button>
                    <h3>Schema Analysis: ${schema.post_type}</h3>
                    <pre class="ucm-schema-viewer">${pretty}</pre>
                </div>
            </div>`).appendTo('body');

            $('#ucm-close-modal').on('click', () => overlay.remove());
        },

        showLoader: function() { this.$loader.fadeIn(); },
        hideLoader: function() { this.$loader.fadeOut(); },

        renderImportSuccess: function(data) {
            const validation = data.validation || {};
            const results = data.results || {};
            const items = Array.isArray(results.items) ? results.items : [];
            const status = data.status || 'completed';
            const isRunning = status === 'queued' || status === 'running';
            const stalledHint = Boolean(data.stalled_hint);
            const heading = isRunning
                ? `Import ${status}.`
                : (data.mode === 'dry-run' ? 'Dry run complete.' : 'Import complete.');
            const warnings = Array.isArray(validation.warnings) ? validation.warnings : [];
            const itemHtml = items.length
                ? `<div class="ucm-result-list">${items.map((item) => `
                    <div class="ucm-result-row">
                        <strong>${this.escapeHtml(item.status || 'processed')}</strong>
                        <span>${this.escapeHtml(item.uuid || '')}</span>
                        <span>${this.escapeHtml(item.message || '')}</span>
                    </div>`).join('')}</div>`
                : '';

            $('#ucm-import-results').html(`
                <div class="ucm-notice ucm-notice-success">
                    <strong>${this.escapeHtml(heading)}</strong>
                    <p>${isRunning ? 'The background worker is still processing this package. You can leave this page and return with the resumable job link.' : 'Review the summary below before moving to the next migration step.'}</p>
                    <div class="ucm-summary-grid">
                        <div class="ucm-summary-card"><strong>${this.escapeHtml(String(validation.summary ? validation.summary.items || 0 : 0))}</strong><span>Items Reviewed</span></div>
                        <div class="ucm-summary-card"><strong>${this.escapeHtml(String(results.imported || 0))}</strong><span>Created</span></div>
                        <div class="ucm-summary-card"><strong>${this.escapeHtml(String(results.updated || 0))}</strong><span>Updated</span></div>
                        <div class="ucm-summary-card"><strong>${this.escapeHtml(String(results.failed || 0))}</strong><span>Failed</span></div>
                    </div>
                    <div class="ucm-panel-meta">
                        <span>Post type: <code>${this.escapeHtml(validation.summary ? validation.summary.post_type || '' : '')}</code></span>
                        <span>Chunk offset: ${this.escapeHtml(String(results.offset || 0))}</span>
                        <span>Next offset: ${this.escapeHtml(String(results.next_offset || 0))}</span>
                        <span>More remaining: ${this.escapeHtml(String(results.has_more || false))}</span>
                    </div>
                    ${stalledHint ? `<p class="ucm-text-light"><strong>No progress has been detected for a while.</strong> Review Diagnostics and confirm the background worker is running before waiting longer.</p>` : ''}
                    ${data.job_id ? `<p>Job ID: <code>${this.escapeHtml(data.job_id)}</code></p>` : ''}
                    ${data.resume_url ? `<p><a href="${this.escapeHtml(data.resume_url)}">Open resumable job link</a></p>` : ''}
                    ${warnings.length ? `<ul class="ucm-detail-list">${warnings.map((warning) => `<li>${this.escapeHtml(warning)}</li>`).join('')}</ul>` : ''}
                    <p class="ucm-text-light">Log: <code>${this.escapeHtml(data.log_path || '')}</code></p>
                    <div class="ucm-inline-links">
                        ${this.buildLogUrl(data.log_path || '') ? `<a href="${this.escapeHtml(this.buildLogUrl(data.log_path || ''))}">Open Log Preview</a>` : ''}
                        <a href="${this.escapeHtml(ucmLocal.urls.diagnostics)}">Open Diagnostics</a>
                        <a href="${this.escapeHtml(ucmLocal.urls.logs)}">Open Logs</a>
                    </div>
                </div>
                ${itemHtml}
            `);
        },

        renderImportFailure: function(response) {
            const data = response.data || {};
            const validation = data.validation || {};
            const errors = Array.isArray(validation.errors) ? validation.errors : [data.message || 'The request failed.'];

            $('#ucm-import-results').html(`
                <div class="ucm-notice ucm-notice-error">
                    <strong>${this.escapeHtml(data.message ? 'Import failed.' : 'Package validation failed.')}</strong>
                    <ul class="ucm-detail-list">${errors.map((error) => `<li>${this.escapeHtml(error)}</li>`).join('')}</ul>
                    ${data.log_path ? `<p class="ucm-text-light">Log: <code>${this.escapeHtml(data.log_path)}</code></p>` : ''}
                    ${data.job_id ? `<p>Job ID: <code>${this.escapeHtml(data.job_id)}</code></p>` : ''}
                    ${data.failed_stage || data.stage ? `<p>Stage: <code>${this.escapeHtml(data.failed_stage || data.stage)}</code></p>` : ''}
                    ${data.error_code ? `<p>Error code: <code>${this.escapeHtml(data.error_code)}</code></p>` : ''}
                    ${data.failure_subsystem ? `<p>Subsystem: <code>${this.escapeHtml(data.failure_subsystem)}</code></p>` : ''}
                    <div class="ucm-inline-links">
                        ${data.resume_url ? `<a href="${this.escapeHtml(data.resume_url)}">Open resumable job link</a>` : ''}
                        ${this.buildLogUrl(data.log_path || '') ? `<a href="${this.escapeHtml(this.buildLogUrl(data.log_path || ''))}">Open Log Preview</a>` : ''}
                        <a href="${this.escapeHtml(ucmLocal.urls.diagnostics)}">Open Diagnostics</a>
                        <a href="${this.escapeHtml(ucmLocal.urls.logs)}">Open Logs</a>
                    </div>
                </div>
            `);
        },

        renderAjaxError: function(selector, response) {
            const message = response && response.data && response.data.message ? response.data.message : 'The request did not complete successfully.';
            this.renderMessage(selector, message, 'error');
        },

        renderRequestError: function(selector, xhr) {
            const payload = xhr.responseJSON || {};
            const message = payload.data && payload.data.message ? payload.data.message : 'An unexpected request error occurred.';
            this.renderMessage(selector, message, 'error');
        },

        renderMessage: function(selector, message, type) {
            const cssClass = type === 'error' ? 'ucm-notice-error' : (type === 'success' ? 'ucm-notice-success' : 'ucm-notice-info');
            $(selector).html(`<div class="ucm-notice ${cssClass}"><p>${this.escapeHtml(message)}</p></div>`);
        },

        setButtonState: function($button, disabled, label) {
            $button.prop('disabled', disabled).text(label);
        },

        setProgress: function(percent) {
            const normalized = Math.max(0, Math.min(100, percent));
            $('#ucm-import-progress-bar').css('width', `${normalized}%`);
            $('.ucm-progress').attr('aria-valuenow', String(normalized));
        },

        calculateProgress: function(data, results) {
            const total = data.validation && data.validation.summary ? Number(data.validation.summary.items || 0) : 0;
            if (!total) {
                return 0;
            }

            const nextOffset = Number(results.next_offset || 0);
            return Math.round((nextOffset / total) * 100);
        },

        buildLogUrl: function(logPath) {
            if (!logPath) {
                return '';
            }

            const normalized = String(logPath).replace(/\\/g, '/');
            const logName = normalized.split('/').pop();
            if (!logName || !/\.txt$/i.test(logName)) {
                return '';
            }

            return `${ucmLocal.urls.logs}&log=${encodeURIComponent(logName)}`;
        },

        escapeHtml: function(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        formatBytes: function(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let value = Number(bytes || 0);
            let unitIndex = 0;

            while (value >= 1024 && unitIndex < units.length - 1) {
                value /= 1024;
                unitIndex += 1;
            }

            return `${value.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
        },

        humanizeSlug: function(value) {
            return String(value || '')
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/\b\w/g, (match) => match.toUpperCase());
        }
    };

    $(document).ready(() => UCM.init());

})(jQuery);
