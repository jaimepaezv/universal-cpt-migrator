# Developer and API Reference

## Core Service Map

### Discovery and Schema

- `UniversalCPTMigrator\Domain\Discovery\DiscoveryService`
  - discovers registered post types
  - applies inclusion and exclusion filters before the admin UI consumes the list
- `UniversalCPTMigrator\Domain\Schema\Analyzer`
  - builds the exportable schema for a post type
  - augments schema output with ACF field information when available
- `UniversalCPTMigrator\Domain\Schema\CompatibilityService`
  - compares incoming package schema against the target site's live schema
  - produces blocking errors and non-blocking warnings for import validation
- `UniversalCPTMigrator\Domain\Schema\SampleGenerator`
  - generates starter/sample payloads for empty post types

### Export and Import

- `UniversalCPTMigrator\Domain\Export\Exporter`
  - builds portable package payloads
  - exports items, schema, media references, and relationship metadata
- `UniversalCPTMigrator\Domain\Import\Validator`
  - validates uploaded JSON or ZIP packages
  - enforces schema compatibility and package-shape expectations
- `UniversalCPTMigrator\Domain\Import\Processor`
  - executes dry-run and write-mode imports
  - processes chunked item batches and returns structured item outcomes
- `UniversalCPTMigrator\Domain\Import\RelationshipMapper`
  - remaps UUID-based post references into local post IDs
  - supports nested ACF structures including repeater, group, and flexible content layouts
- `UniversalCPTMigrator\Domain\Import\MediaSideloadService`
  - resolves manifest-backed local media files and remote media URLs
  - enforces MIME, image-content, host-policy, and dedupe rules

### Application and Admin

- `UniversalCPTMigrator\Application\ImportRequestService`
  - normalizes upload payloads and import request parameters
- `UniversalCPTMigrator\Application\ImportApplicationService`
  - coordinates synchronous import validation and execution
- `UniversalCPTMigrator\Application\AdminJobService`
  - coordinates async import/export job actions for admin endpoints
- `UniversalCPTMigrator\Infrastructure\AsyncJobService`
  - persists and exposes async job status
- `UniversalCPTMigrator\Infrastructure\BackgroundWorker`
  - queues and executes import/export work through WP-Cron hooks
- `UniversalCPTMigrator\Infrastructure\DiagnosticsService`
  - summarizes queue health, failure state, remediation signals, and job attention rows

## Admin AJAX Actions

Authenticated WordPress admin AJAX actions currently include:

- `ucm_discover_cpts`
- `ucm_get_schema`
- `ucm_generate_sample`
- `ucm_trigger_export`
- `ucm_validate_import`
- `ucm_run_import`
- `ucm_resume_import`
- `ucm_get_job_status`

All state-changing actions require:

- an authenticated user
- appropriate capability checks
- the `ucm_admin_nonce` nonce

## Admin POST Actions

WordPress `admin-post.php` actions currently include:

- `ucm_download_export`
- `ucm_run_cleanup_now`
- `ucm_run_cron_sanity`
- `ucm_cleanup_stale_jobs`

These actions require standard WordPress capability and nonce validation before mutating state or serving artifacts.

### Export Download Probe

`ucm_download_export` also supports a metadata-only probe mode:

- query parameter: `ucm_probe=1`
- still requires the same authenticated capability and valid nonce
- returns JSON metadata instead of streaming the ZIP body

This is used by browser validation to confirm that the download endpoint resolves a real ZIP artifact without depending on flaky browser attachment handling.

## Background Hooks

The plugin registers these scheduled hooks:

- `ucm_process_export_job`
- `ucm_process_import_job`
- `ucm_cleanup_jobs_and_artifacts`

These should remain active in tests and production. Browser and PHPUnit harnesses suppress unrelated core update jobs, not plugin jobs.

## Job State Shape

Persisted background job state may include:

- `type`
- `status`
- `stage`
- `failed_stage`
- `progress`
- `post_type`
- `package`
- `validation`
- `mode`
- `offset`
- `results`
- `artifacts`
- `download_url`
- `extract_dir`
- `log_path`
- `error`
- `error_code`
- `error_context`
- `error_data`
- `failure_category`
- `failure_subsystem`
- `remediation_key`
- `retryable`
- `created_at`
- `updated_at`

Expected `status` values:

- `queued`
- `running`
- `completed`
- `failed`

Expected `stage` values commonly include:

- `queued`
- `analyzing_source`
- `build_package`
- `packaging_bundle`
- `processing_chunk`
- `cleanup_bundle`
- `completed`
- `failed`

## Item Result and Warning Shape

Import item results may include:

- `status`
- `message`
- `post_id`
- `uuid`
- `warnings`

Warning entries are designed to stay machine-readable and may include:

- `code`
- `message`
- `subsystem`
- `context`

Typical warning codes:

- `ucm_media_manifest_missing`
- `ucm_media_manifest_invalid_type`
- `ucm_media_manifest_mime_mismatch`
- `ucm_media_manifest_invalid_image_content`

## Settings Option Reference

The plugin stores settings in the `ucm_settings` option.

Current keys:

- `chunk_size`
- `log_retention_days`
- `artifact_retention_days`
- `temp_retention_days`
- `job_retention_days`
- `delete_data_on_uninstall`
- `allow_remote_media`
- `allowed_media_hosts`

## Extension Hooks

Current filters:

- `u_cpt_mgr_include_builtin_types`
- `u_cpt_mgr_exclude_types`
- `u_cpt_mgr_discovered_cpts`
- `u_cpt_mgr_cpt_schema`
- `u_cpt_mgr_export_data`
- `u_cpt_mgr_meta_allowlist`
- `u_cpt_mgr_sample_payload`
- `ucm_enable_php_error_log`

Hook expectations:

- keep package output portable
- do not introduce environment-specific post ID assumptions
- keep returned values serializable and deterministic
- preserve structured warnings and diagnostics metadata

## Testing Entry Points

Primary validation commands:

```powershell
.\vendor\bin\phpunit --configuration phpunit.xml.dist
npx playwright test --reporter=line --workers=1
```

Supporting references:

- [Testing and validation](testing.md)
- [Benchmark profiles](benchmark-profiles.md)
- [Operations guide](operations.md)
