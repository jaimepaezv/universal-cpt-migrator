# Extensibility and Settings

## Operational Settings

Current plugin settings include:

- `chunk_size`
- `log_retention_days`
- `artifact_retention_days`
- `temp_retention_days`
- `job_retention_days`
- `delete_data_on_uninstall`
- `allow_remote_media`
- `allowed_media_hosts`

These are stored in the `ucm_settings` option.

## Extension Hooks

Current filters exposed by the plugin include:

- `u_cpt_mgr_include_builtin_types`
- `u_cpt_mgr_exclude_types`
- `u_cpt_mgr_discovered_cpts`
- `u_cpt_mgr_cpt_schema`
- `u_cpt_mgr_export_data`
- `u_cpt_mgr_meta_allowlist`
- `u_cpt_mgr_sample_payload`
- `ucm_enable_php_error_log`

See also:

- [Developer and API reference](api-reference.md)
- [Operations guide](operations.md)

Typical uses:

- include default WordPress content types in discovery
- exclude implementation-specific CPTs from export/import UI
- normalize or reorder discovered types before they reach the admin UI
- augment or constrain schema output
- enrich or redact exported item payloads before bundling
- allow additional safe meta keys into exported packages
- customize generated sample payloads for empty post types
- suppress mirrored PHP error-log output in constrained environments

## Developer Expectations

When extending the plugin:

- preserve UUID-based portability
- avoid introducing environment-specific post ID dependencies
- keep import validation deterministic
- keep background job state serializable and safe for `wp_options`
- prefer additive hooks over invasive controller changes
- keep item-level warnings structured and machine-readable when adding new failure paths
- keep diagnostics-friendly metadata available for any new background failure modes
- document any new public hook, setting, or job-state field in the API reference

## Migration Safety Guidelines

Extensions should not:

- trust imported WordPress IDs from another environment
- add unsafe ZIP extraction behavior
- silently bypass MIME or host validation
- add state-changing admin actions without nonce and capability checks
