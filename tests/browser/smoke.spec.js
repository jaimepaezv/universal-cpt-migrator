const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

const fixturePath = path.resolve(__dirname, '..', 'tmp', 'browser-import-package.json');

async function getAdminContext(page) {
  return page.locator('.ucm-wrap').evaluate((node) => ({
    ajaxUrl: node.dataset.ucmAjaxUrl,
    nonce: node.dataset.ucmNonce,
  }));
}

async function postAjax(page, action, extra = {}) {
  const context = await getAdminContext(page);

  return page.evaluate(async ({ ajaxUrl, nonce, action, extra }) => {
    const body = new URLSearchParams({ action, nonce });
    Object.entries(extra).forEach(([key, value]) => {
      body.append(key, String(value));
    });

    const response = await fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body,
    });

    return {
      status: response.status,
      json: await response.json(),
    };
  }, { ...context, action, extra });
}

async function postAjaxWithUpload(page, action) {
  const context = await getAdminContext(page);

  return page.evaluate(async ({ ajaxUrl, nonce, action }) => {
    const input = document.querySelector('#ucm-package-upload');
    if (!input || !input.files || !input.files.length) {
      throw new Error('No package selected for upload.');
    }

    const formData = new FormData();
    formData.append('action', action);
    formData.append('nonce', nonce);
    formData.append('package', input.files[0]);

    const response = await fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    });

    return {
      status: response.status,
      json: await response.json(),
    };
  }, { ...context, action });
}

async function pollJob(page, jobId, attempts = 30) {
  for (let i = 0; i < attempts; i += 1) {
    const response = await postAjax(page, 'ucm_get_job_status', { job_id: jobId });
    expect(response.status).toBe(200);
    expect(response.json.success).toBeTruthy();

    const state = response.json.data;
    if (state.status === 'completed' || state.status === 'failed') {
      return state;
    }

    await page.waitForTimeout(1000);
  }

  throw new Error(`Timed out waiting for job ${jobId} to finish.`);
}

test.describe('Universal CPT Migrator admin browser workflows', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await expect(page).toHaveURL(/wp-admin/);
  });

  test('dashboard page renders discovery shell', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator');
    await expect(page.locator('h1')).toContainText('Universal CPT Migrator');
    await expect(page.locator('text=Discovery Engine')).toBeVisible();
    const pageNav = page.getByLabel('Universal CPT Migrator navigation');
    await expect(pageNav.getByRole('link', { name: 'Dashboard' })).toBeVisible();
    await expect(pageNav.getByRole('link', { name: 'Export' })).toBeVisible();
  });

  test('export workflow queues and completes a background package build', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-export');

    const exportResponse = await postAjax(page, 'ucm_trigger_export', { post_type: 'post' });
    expect(exportResponse.status).toBe(200);
    expect(exportResponse.json.success).toBeTruthy();

    const state = await pollJob(page, exportResponse.json.data.job_id);
    expect(state.status).toBe('completed');
    expect(state.download_url).toContain('admin-post.php?action=ucm_download_export');
    expect(state.package.metadata.post_type).toBe('post');
  });

  test('export screen shows contextual details for the selected content type', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-export');

    await page.waitForFunction(() => {
      const select = document.querySelector('#ucm-export-post-type');
      return !!select && !!select.querySelector('option[value="post"]');
    });
    await page.selectOption('#ucm-export-post-type', 'post');

    const profile = page.locator('#ucm-export-type-profile');
    await expect(profile).toContainText('post');
    await expect(profile).toContainText('Records:');
    await expect(profile).toContainText('REST:');
    await expect(profile).toContainText('Supports');
  });

  test('export screen performs a direct ZIP download instead of queueing a background job', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-export');

    await page.waitForFunction(() => {
      const select = document.querySelector('#ucm-export-post-type');
      return !!select && !!select.querySelector('option[value="post"]');
    });
    await page.selectOption('#ucm-export-post-type', 'post');

    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Export ZIP Now' }).click();
    const download = await downloadPromise;

    expect(download.suggestedFilename()).toMatch(/post-.*\.zip$/);
    await expect(page.locator('#ucm-export-status')).toContainText('direct export', { timeout: 10000 });
  });

  test('import workflow validates and runs a full background import from a local package', async ({ page }) => {
    expect(fs.existsSync(fixturePath)).toBeTruthy();

    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-import');
    await expect(page.locator('#ucm-resume-import')).toBeDisabled();
    await page.setInputFiles('#ucm-package-upload', fixturePath);
    await expect(page.locator('#ucm-package-profile')).toContainText('browser-import-package.json');
    await expect(page.locator('#ucm-package-profile')).toContainText('JSON package');

    const dryRunResponse = await postAjaxWithUpload(page, 'ucm_validate_import');
    expect(dryRunResponse.status).toBe(200);
    expect(dryRunResponse.json.success).toBeTruthy();
    expect(dryRunResponse.json.data.mode).toBe('dry-run');
    expect(dryRunResponse.json.data.validation.summary.post_type).toBe('post');

    await page.setInputFiles('#ucm-package-upload', fixturePath);
    const importResponse = await postAjaxWithUpload(page, 'ucm_run_import');
    expect(importResponse.status).toBe(200);
    expect(importResponse.json.success).toBeTruthy();

    const state = await pollJob(page, importResponse.json.data.job_id);
    expect(state.status).toBe('completed');
    expect(state.results.imported + state.results.updated).toBeGreaterThanOrEqual(1);
  });

  test('failed import recovery workflow opens the job from diagnostics and surfaces remediation details', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-diagnostics');

    const retryableRow = page.locator('.ucm-job-table-row').filter({ hasText: 'import_chunk_processor' }).first();
    await expect(retryableRow).toBeVisible();
    await retryableRow.getByRole('link', { name: 'Open import job' }).click();

    await expect(page).toHaveURL(/page=u-cpt-migrator-import&ucm_job_id=/);
    await expect(page.locator('#ucm-resume-import')).toHaveAttribute('data-job-id', /.+/);
    await expect(page.locator('#ucm-import-results')).toContainText('Import failed.', { timeout: 30000 });
    await expect(page.locator('#ucm-import-results')).toContainText('Stage:');
    await expect(page.locator('#ucm-import-results')).toContainText('processing_chunk');
    await expect(page.locator('#ucm-import-results')).toContainText('Subsystem:');
    await expect(page.locator('#ucm-import-results')).toContainText('import_chunk_processor');
    const openLogPreviewLink = page.getByRole('link', { name: 'Open Log Preview' }).last();
    await expect(openLogPreviewLink).toBeVisible();
    await openLogPreviewLink.click();
    await expect(page).toHaveURL(/page=u-cpt-migrator-logs/);
    await expect(page.locator('pre.ucm-schema-viewer')).toContainText('Browser retryable import fixture log');
  });

  test('failed export remediation surfaces diagnostics and log preview details', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-diagnostics');

    const failedExportRow = page.locator('.ucm-job-table-row').filter({ hasText: 'package_transport' }).first();
    await expect(failedExportRow).toBeVisible();
    await expect(failedExportRow).toContainText('Category: transport');
    await expect(failedExportRow).toContainText('Subsystem: package_transport');
    await failedExportRow.getByRole('link', { name: 'Open log preview' }).click();

    await expect(page).toHaveURL(/page=u-cpt-migrator-logs/);
    await expect(page.locator('pre.ucm-schema-viewer')).toContainText('Browser export packaging fixture log');
  });

  test('long-running import resume state is surfaced when opening the job from diagnostics', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-diagnostics');

    const runningImportRow = page.locator('.ucm-job-table-row').filter({ hasText: 'Progress: 50%, offset 25 of 50' }).first();
    await expect(runningImportRow).toBeVisible();
    await runningImportRow.getByRole('link', { name: 'Open import job' }).click();

    await expect(page).toHaveURL(/page=u-cpt-migrator-import&ucm_job_id=/);
    await expect(page.locator('#ucm-import-results')).toContainText('Import running.', { timeout: 30000 });
    await expect(page.locator('#ucm-import-results')).toContainText('Job ID:');
    await expect(page.locator('#ucm-import-results')).toContainText('Chunk offset: 25');
    await expect(page.locator('#ucm-import-results')).toContainText('More remaining: true');
  });

  test('diagnostics workflow shows actionable failures and runs repair actions', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-diagnostics');

    await expect(page.locator('h1')).toContainText('Diagnostics');
    await expect(page.locator('text=Jobs Requiring Attention')).toBeVisible();
    await expect(page.getByText('Category: media').first()).toBeVisible();
    await expect(page.getByText('Subsystem: media_manifest_content_validation').first()).toBeVisible();

    await page.click('button:has-text("Run Worker Sanity Check")');
    await expect(page.locator('text=Worker sanity check completed.')).toBeVisible({ timeout: 30000 });

    await page.click('button:has-text("Clear Stale Queued Jobs")');
    await expect(page.locator('text=Removed')).toBeVisible({ timeout: 30000 });
  });

  test('diagnostics workflow opens the related log preview for failed jobs', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-diagnostics');

    await expect(page.locator('text=Browser remediation fixture log')).not.toBeVisible();
    const mediaFailureRow = page.locator('.ucm-job-table-row').filter({ hasText: 'media_manifest_content_validation' }).first();
    await expect(mediaFailureRow).toBeVisible();
    await mediaFailureRow.getByRole('link', { name: 'Open log preview' }).click();

    await expect(page).toHaveURL(/page=u-cpt-migrator-logs/);
    await expect(page).toHaveURL(/job_id=/);
    await expect(page.locator('h1')).toContainText('Logs');
    await expect(page.locator('pre.ucm-schema-viewer')).toContainText('Browser remediation fixture log');
    await expect(page.locator('pre.ucm-schema-viewer')).toContainText('Packaged image bytes did not match the declared image type.');
  });

  test('logs workflow lists and previews stored plugin logs', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-logs');

    await expect(page.locator('h1')).toContainText('Logs');
    await expect(page.locator('#ucm-log-list .ucm-log-link').filter({ hasText: 'browser-fixture-log.txt' })).toBeVisible();
    await page.fill('#ucm-log-search', 'retryable');
    await expect(page.locator('#ucm-log-list .ucm-log-link').filter({ hasText: 'browser-retryable-log.txt' })).toBeVisible();
    await expect(page.locator('#ucm-log-list .ucm-log-link').filter({ hasText: 'browser-fixture-log.txt' })).toBeHidden();
    await page.fill('#ucm-log-search', '');
    await page.locator('#ucm-log-list .ucm-log-link').filter({ hasText: 'browser-fixture-log.txt' }).click();

    await expect(page.locator('pre.ucm-schema-viewer')).toContainText('Browser remediation fixture log');
    await expect(page.locator('pre.ucm-schema-viewer')).toContainText('Packaged image bytes did not match the declared image type.');
    await page.fill('#ucm-log-search', 'declared image type');
    await page.selectOption('#ucm-log-level', 'ERROR');
    await page.getByRole('button', { name: 'Apply Trace Filters' }).click();
    await expect(page.locator('pre.ucm-schema-viewer')).toContainText('Packaged image bytes did not match the declared image type.');
  });

  test('import recovery links navigate to diagnostics and logs screens', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-import');

    await page.getByRole('link', { name: 'Open Diagnostics' }).click();
    await expect(page).toHaveURL(/page=u-cpt-migrator-diagnostics/);
    await expect(page.locator('h1')).toContainText('Diagnostics');

    await page.goto('/wp-admin/admin.php?page=u-cpt-migrator-import');
    await page.getByRole('link', { name: 'Open Logs' }).click();
    await expect(page).toHaveURL(/page=u-cpt-migrator-logs/);
    await expect(page.locator('h1')).toContainText('Logs');
  });
});
