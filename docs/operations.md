# Operations Guide

## Recommended Migration Workflow

1. Export the source content type from **Export Items**.
2. Wait for the background export to finish and download the ZIP artifact.
3. Upload the package on the destination site from **Import Packages**.
4. Run **Validate Package (Dry Run)** first.
5. Review validation warnings, schema compatibility, and media policy.
6. Run the full import only after the dry run is acceptable.
7. Use the stored log path and Diagnostics page if the job stalls or fails.

## Long-Running Jobs

Imports and exports are processed in the background through WP-Cron.

Operational notes:

- Jobs may continue after you leave the page.
- Import pages expose resumable job links for active or recoverable jobs.
- Diagnostics reports queued, running, failed, stale, and unscheduled jobs.
- Job records also retain stage, failure category, subsystem, remediation key, retryability, and item warning counts.

## Job Stages

Current stage values used operationally include:

- `queued`
- `analyzing_source`
- `build_package`
- `packaging_bundle`
- `processing_chunk`
- `cleanup_bundle`
- `completed`
- `failed`

Use the stored failed stage together with the subsystem and remediation key to decide whether to resume, rebuild, or re-upload.

## Failure Classification

Diagnostics can currently classify failures into:

- `media`
- `transport`
- `export`
- `import`
- `acf`
- `item_data`
- `state`

Common subsystems include:

- `media_manifest_lookup`
- `media_manifest_type_validation`
- `media_manifest_content_validation`
- `media_remote_policy`
- `media_remote_sideload`
- `package_transport`
- `export_serializer`
- `import_chunk_processor`
- `job_bootstrap`

## When a Job Stalls

Check these in order:

1. Open **Diagnostics** and confirm whether WP-Cron is disabled.
2. Check whether the queued job is missing a worker event.
3. Run **Worker Sanity Check** to restore missing events.
4. Inspect the stored log path from the import/export status panel.
5. If a queued job is clearly abandoned, use **Clear Stale Queued Jobs**.

## When a Job Fails

Review these fields together:

- failed stage
- failure category
- failure subsystem
- remediation key
- retryable flag
- item warnings or failed item count

Typical responses:

- `check_packaged_media_manifest`: verify bundled media files, declared MIME values, and image bytes
- `review_remote_media_policy`: confirm allowlisted hosts and remote-media policy
- `inspect_package_transport`: inspect ZIP/JSON structure and extraction safety
- `resume_or_reupload_import`: resume if state is valid, otherwise rebuild or re-upload
- `review_source_schema_and_media`: re-check the source schema and media accessibility
- `requeue_job_payload`: inspect the stored job payload because the queued state was incomplete

## Retention and Cleanup

The plugin stores data under the WordPress uploads directory in a protected `u-cpt-mgr` folder.

Retention-controlled areas:

- export artifacts
- import artifacts
- temporary extraction directories
- background job records
- logs

Use **Settings** to configure retention windows. Use **Diagnostics** to run cleanup immediately.

## Media Policy

For safest migrations:

- prefer ZIP packages with bundled media
- keep remote media disabled unless explicitly needed
- configure an allowlist when remote media must be fetched

Featured media imports reject:

- missing packaged files
- non-image files
- fake image content
- MIME mismatches
- URLs outside the configured host allowlist

If media import fails but the rest of the item is valid, the item can still be imported with a warning instead of failing the entire job.
