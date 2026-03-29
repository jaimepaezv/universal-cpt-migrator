# Universal CPT Migrator

Production-grade WordPress plugin to discover custom post types, analyze schema, export content and schema, validate migration packages, and import data safely with UUID-based upserts, relationship remapping, packaged media support, diagnostics, and operator-friendly admin workflows.

## Why This Plugin Exists

Moving CPT-heavy WordPress sites between environments is usually brittle:

- post IDs are not portable
- nested ACF relationships break easily
- media references are inconsistent
- schema drift is hard to detect before import
- large imports need resumable processing and diagnostics

Universal CPT Migrator is built to solve those problems with a real migration workflow instead of ad hoc JSON dumps.

## Highlights

- Runtime CPT discovery for registered post types
- Deep schema analysis including ACF-aware structures when available
- Export to JSON and ZIP packages
- Direct ZIP export from the admin UI
- Dry-run validation before import
- Background import jobs for large datasets
- UUID-based upsert behavior
- Nested relationship remapping
- Packaged media transport with local manifest resolution
- Media dedupe and stricter image validation
- Logs, diagnostics, retention cleanup, and resumable job workflows
- Admin UX for discovery, export, import, logs, diagnostics, and settings

## Feature Overview

| Area | Status |
| --- | --- |
| CPT discovery | Implemented |
| Schema analysis | Implemented |
| Sample payload generation | Implemented |
| Direct export download | Implemented |
| JSON and ZIP package import | Implemented |
| Dry-run validation | Implemented |
| Background import jobs | Implemented |
| Packaged media import | Implemented |
| Relationship remapping | Implemented |
| Diagnostics and logs | Implemented |
| Settings and retention cleanup | Implemented |

## Core Migration Model

### Export

The plugin inspects the selected post type, builds a package, and writes:

- metadata
- schema information
- items
- taxonomies
- ACF payloads when available
- meta
- featured media manifest data

ZIP exports include:

- `package.json`
- packaged media files under `media/`

### Validate

Dry-run validation checks:

- package readability
- target post type registration
- item structure
- schema compatibility signals
- portability warnings
- media-related warnings when detectable

### Import

The importer:

- uses `_ucm_uuid` as the stable identity key
- updates existing records when UUIDs match
- creates new records when they do not
- remaps supported relationships
- resolves taxonomies
- imports or reuses featured media
- records warnings and failure metadata for diagnostics

## Admin Screens

### Dashboard

- discovered content types
- quick discovery filters
- schema analysis access
- sample package generation

### Export Items

- direct ZIP export
- content-type profile preview
- immediate artifact download

### Import Packages

- JSON or ZIP upload
- dry-run validation
- resumable background import jobs
- result summaries with links to diagnostics and logs

### Logs

- log file listing
- preview pane
- trace filtering

### Diagnostics

- queue health
- failed and stale job visibility
- worker sanity repair
- cleanup actions

### Settings

- chunk size
- retention windows
- remote media policy controls

## ACF Support

When ACF is available on the site, the plugin supports field-aware export/import handling for common structures including:

- relationship
- post object
- page link
- taxonomy
- user
- image
- file
- gallery
- group
- repeater
- flexible content

Nested relationship remapping and structured portability are supported where the field map is available.

## Media Handling

- featured media can be exported with packaged file manifests
- ZIP bundles can carry local binaries for import without remote fetch
- media dedupe reuses existing attachments by content hash when possible
- invalid media payloads generate structured warnings or failures

## Installation

### WordPress Plugin ZIP

1. Download the installable plugin ZIP from the release artifacts.
2. In WordPress admin, go to `Plugins > Add New > Upload Plugin`.
3. Upload the ZIP.
4. Activate **Universal CPT Migrator**.

### Manual Installation

1. Copy the `universal-cpt-migrator` folder into `/wp-content/plugins/`.
2. Activate the plugin from the WordPress admin.
3. Open **CPT Migrator** in the admin menu.

## Basic Usage

### Export a CPT

1. Open `CPT Migrator > Export`.
2. Select the content type.
3. Click `Export ZIP Now`.
4. Save the ZIP artifact.

### Validate a Package

1. Open `CPT Migrator > Import`.
2. Upload a `.json` or `.zip` package.
3. Click `Validate Package (Dry Run)`.
4. Review errors and warnings before importing.

### Run a Full Import

1. Upload the package.
2. Click `Run Full Import`.
3. Monitor progress from the import screen.
4. Use Diagnostics and Logs if the job stalls or fails.

## Filters and Extension Points

The plugin already exposes practical extension hooks including:

- `u_cpt_mgr_include_builtin_types`
- `u_cpt_mgr_exclude_types`
- `u_cpt_mgr_discovered_cpts`
- `u_cpt_mgr_cpt_schema`
- `u_cpt_mgr_export_data`
- `u_cpt_mgr_meta_allowlist`
- `u_cpt_mgr_meta_relationship_keys`
- `u_cpt_mgr_sample_payload`
- `ucm_enable_php_error_log`

## Documentation

Detailed project documentation lives in:

- [Admin workflows](docs/admin-workflows.md)
- [API reference](docs/api-reference.md)
- [Benchmark profiles](docs/benchmark-profiles.md)
- [Extensibility](docs/extensibility.md)
- [Operations guide](docs/operations.md)
- [Package format](docs/package-format.md)
- [Testing and validation](docs/testing.md)

## Testing

The repository includes:

- WordPress integration tests with PHPUnit
- browser workflow validation with Playwright
- scale and benchmark-oriented test scenarios

Typical commands:

```bash
composer install
npm install
./vendor/bin/phpunit --configuration phpunit.xml.dist
npx playwright test
```

## Repository Structure

```text
assets/       Admin CSS and JS
docs/         Project documentation
scripts/      Local bootstrap and packaging helpers
src/          Plugin source code
templates/    WordPress admin templates
tests/        PHPUnit, browser, and sandbox fixtures
```

## Requirements

- WordPress 6.x
- PHP 7.4+ recommended minimum for current codebase behavior
- ACF optional, but recommended when migrating ACF-driven content models

## Notes

- Dry-run validation should be used before every real import.
- ZIP is the preferred format when media portability matters.
- Large imports depend on working WP-Cron or equivalent background processing.
- Diagnostics should be the first stop when jobs remain queued, stale, or failed.

## License

Use the license that matches your intended distribution model before publishing this repository publicly.
