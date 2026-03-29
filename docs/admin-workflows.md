# Admin Workflows

## Dashboard

The dashboard is the operator entry point for discovery and schema inspection.

Primary uses:

- confirm which post types are currently discoverable
- inspect schema output before building packages
- generate a sample payload when a post type has no source records

Operator guidance:

- treat the discovery list as runtime truth, not as a static configuration
- use generated sample payloads to review field shape before the first migration
- review schema output before introducing new ACF field structures on the source site

## Export Workflow

Open **CPT Migrator > Export** to build a portable package.

Workflow:

1. Select the source content type.
2. Queue the export.
3. Wait for the background job to complete.
4. Download the ZIP artifact from the status panel or Diagnostics.
5. Verify the artifact downloads successfully before retention cleanup removes it.

Browser and automated validation can also use the authenticated download probe to confirm that the export artifact still resolves correctly without consuming the binary body.

What the export includes:

- package metadata
- analyzed schema
- exported items
- bundled media files under `media/` when featured media exists

Operator guidance:

- prefer ZIP exports when media portability matters
- keep artifact retention high enough to preserve the bundle until the destination site is verified
- if export fails during packaging, inspect the stored log path and uploads permissions first

## Import Workflow

Open **CPT Migrator > Import** to validate and apply a package.

Workflow:

1. Upload a `.json` or `.zip` package.
2. Run **Validate Package (Dry Run)**.
3. Review validation errors, warnings, and schema compatibility output.
4. Run **Full Import** only when the dry run is acceptable.
5. Use the resumable job link if the job continues after leaving the page.
6. If Diagnostics exposes a retryable failed import, use **Open import job** to return to the Import screen with the relevant job preloaded.

Behavior notes:

- imports are UUID-based and idempotent within the destination site
- existing items with matching `_ucm_uuid` are updated instead of duplicated
- large imports continue through background chunks
- item-level media failures can complete as warnings without failing the whole job
- failed import panels link directly to Diagnostics and Logs for remediation review

When to stop and investigate:

- schema incompatibility errors
- repeated media manifest warnings
- queued jobs with no worker event
- running jobs that stop advancing offset or progress

## Diagnostics Workflow

Open **CPT Migrator > Diagnostics** whenever a job stalls, fails, or completes with warnings.

Diagnostics highlights:

- cron health
- queue health
- jobs requiring attention
- failure breakdown
- recent jobs

Available repair actions:

- **Run Retention Cleanup**
- **Run Worker Sanity Check**
- **Clear Stale Queued Jobs**

Recommended escalation order:

1. confirm WP-Cron health
2. repair missing worker events
3. inspect failed stage, subsystem, error code, and remediation key
4. open logs
5. retry, resume, or rebuild the package only after the root cause is understood

## Settings Workflow

Open **CPT Migrator > Settings** to adjust operational behavior.

Key settings:

- chunk size
- log retention days
- artifact retention days
- temp retention days
- job retention days
- remote media policy
- allowed media hosts
- uninstall cleanup behavior

Practical defaults:

- smaller chunk size for constrained hosts
- larger retention windows for production cutovers
- remote media disabled unless there is a clear need

## Logs Workflow

Open **CPT Migrator > Logs** when a job fails or completes with warnings.

Use logs to correlate:

- failed stage
- error code
- affected item offset
- packaged media problems
- retry or resume timing
