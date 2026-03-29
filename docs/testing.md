# Testing and Validation

## Test Layers

The plugin is currently validated through three main layers:

- WordPress integration tests with PHPUnit
- browser-level admin workflow tests with Playwright
- larger-scale import/export tests for chunking, media, and worker recovery
- documented benchmark profiles for denser media and slower-I/O-style chunk scenarios

## PHPUnit

Run:

```powershell
.\vendor\bin\phpunit --configuration phpunit.xml.dist
```

Coverage areas include:

- schema compatibility validation
- nested relationship remapping
- media dedupe and media import failure handling
- ZIP transport validation
- admin AJAX and `admin_post` flows
- diagnostics repair actions
- background import/export execution
- chunked resume and worker-event recovery
- dense-media and slower-I/O-style workload profiles

## Browser Validation

Run:

```powershell
npx playwright test --reporter=line --workers=1
```

The browser harness:

- boots a dedicated local WordPress instance
- uses SQLite only for the browser-test environment
- seeds fixture jobs and content
- seeds remediation logs for diagnostics and log-preview workflows
- validates dashboard, export, import, and diagnostics workflows against a real admin session
- validates log preview access from the diagnostics surface
- validates failed-import recovery navigation from diagnostics into import and logs
- validates completed export download from the actual admin UI
- validates the authenticated export download probe for browser-safe artifact verification

The browser environment suppresses noisy core update/transient cron callbacks that are not relevant to plugin validation, while keeping plugin background jobs active.

## Scale Validation

Scale tests currently validate:

- media-heavy export bundles
- chunked background imports
- worker-event loss and repair
- large item counts across multiple chunks
- successful completion with item-level warnings
- dense media on small chunk sizes that simulate slower I/O environments

These tests are designed to catch:

- queue starvation
- broken resume logic
- bundle extraction cleanup regressions
- media manifest regressions under high volume

## Recommended Local Validation Sequence

1. Run PHPUnit.
2. Run Playwright.
3. Review logs under the plugin storage directory if any failure occurs.
4. Re-run the failing scenario after cleanup to ensure the result is deterministic.

## Browser Harness Notes

The browser harness uses:

- `tests/browser/bootstrap-site.php`
- `tests/browser/router.php`
- `tests/browser-site/wp-config.php`

Important constraints:

- the browser harness should remain isolated from production settings
- plugin cron hooks must remain active in browser tests
- only irrelevant core cron callbacks should be silenced there

## Performance Interpretation

Passing scale tests does not guarantee behavior on every production host. They do provide confidence that:

- chunking remains functional
- recovery tools work
- large packages remain logically portable

Use real staging environments for final capacity verification on very large sites.

See also:

- [Benchmark profiles](benchmark-profiles.md)
- [Developer and API reference](api-reference.md)
