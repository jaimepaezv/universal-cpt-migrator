# Package Format

Universal CPT Migrator exports either:

- `package.json` for JSON-only workflows
- a ZIP bundle containing `package.json` plus bundled media files under `media/`

## Top-Level Structure

```json
{
  "metadata": {
    "post_type": "project",
    "generated_at": "2026-03-28T12:00:00Z"
  },
  "schema": {
    "post_type": "project"
  },
  "items": []
}
```

## `metadata`

Expected keys:

- `plugin`: plugin name recorded at export time
- `version`: plugin version recorded at export time
- `post_type`: source content type slug
- `generated`: UTC timestamp for package generation
- `item_count`: total exported items
- `chunk_size`: chunk size used when building the package
- `total_chunks`: total export chunks represented in the package

## `schema`

The schema section represents the analyzed content model for the exported post type, including:

- registered taxonomies
- exported post meta
- ACF field definitions when ACF is available
- compatibility-relevant structure used during dry-run validation

## `items`

Each item is an exported content record. Core keys currently used by the importer include:

- `uuid`
- `post_type`
- `post_title`
- `post_content`
- `post_excerpt`
- `post_status`
- `post_date`
- `post_name`
- `post_author`
- `meta`
- `taxonomies`
- `acf`
- `featured_media`
- `relationships`

## Featured Media Payload

```json
{
  "featured_media": {
    "url": "https://example.com/uploads/hero.gif",
    "title": "Hero image",
    "alt": "Project hero",
    "content_hash": "sha1-or-similar",
    "manifest": {
      "relative_path": "media/hero.gif",
      "filename": "hero.gif",
      "mime_type": "image/gif",
      "content_hash": "sha1-or-similar"
    }
  }
}
```

Behavior:

- ZIP imports prefer bundled local media before remote fetch.
- Existing attachments are deduped by content hash before URL.
- Featured media imports only accept valid image files.
- Manifest imports reject missing files, non-image types, declared/detected MIME mismatches, and fake image contents.

## Relationship Payloads

Relationships should not depend on local WordPress IDs across environments. Exported relationship payloads may contain UUID-based structures that are remapped during import.

Nested relationship remapping is supported for:

- ACF `relationship`
- ACF `post_object`
- ACF `page_link`
- nested ACF groups
- repeater rows
- flexible content layouts

## ZIP Bundle Layout

```text
package.json
media/...
```

Rejected ZIP conditions:

- path traversal entries such as `../evil.php`
- malformed `package.json`
- unsafe manifest-relative paths

## Import Expectations

- Imports are UUID-based and idempotent within the target site.
- Dry-run validation can block imports on schema incompatibility.
- Background imports may process the package in chunks based on plugin settings.
- Completed imports can include item-level warnings without failing the whole job.

## Item Result Semantics

Item results emitted during import can include:

- `status`
- `post_id`
- `updated`
- `message`
- `warnings`

Warning entries currently include:

- `code`
- `subsystem`
- `message`
- `context`
