# ADR 0010: Versioned schemas and immutable entry snapshots

## Status

Accepted.

## Decision

Custom content is split into versioned content-type schemas, mutable entry drafts and
immutable published entry snapshots. A content type owns a stable UUID, public API
name and at most 50 ordered field definitions. Field UUIDs and lower-camel-case keys
are unique within the type. Both schema updates and entry updates use integer
optimistic-lock versions so a stale editor cannot overwrite newer work.

Entry values are a closed scalar map. Values may only be strings, numbers, booleans
or null; arrays and nested objects are rejected. The current content-type schema
determines the allowed keys, required values and runtime scalar type. `date` values
are ISO calendar-date strings and `media` values are media asset UUID strings.
Unknown fields are rejected. Entry slugs are unique within one content type rather
than globally.

Publishing validates the exact entry version against the current content-type
schema, then copies the entry and that schema version into an immutable snapshot.
The `(entry_id, source_version)` pair is unique, making repeated publication
idempotent. Later edits to either the entry or content type cannot change an already
published response. Public API collection and single-entry reads use only active
snapshots; the single response uses its snapshot UUID as the ETag and supports
conditional `304 Not Modified` responses.

Public delivery does not verify a type against the mutable authoring schema. A
collection request for an API name with no active snapshots returns an empty page;
a single-entry request with no active snapshot returns `404`. Renaming or otherwise
editing a content type therefore cannot hide or change an already active snapshot.

The full-CMS surface resolves the same active snapshot at
`/content/{type}/{slug}` and renders it through a template/view-model adapter. This
HTML route is intentionally not part of the OpenAPI document, while the headless API
lives under `/api/v1/content/entries`.

## Consequences

- custom content can evolve without allowing incomplete drafts or schema edits to
  leak into public delivery;
- published payloads remain reproducible because every snapshot embeds its schema;
- schema and entry data are duplicated in snapshots, which is accepted for stable,
  cacheable reads and future rollback;
- changing or removing a field can make an old draft invalid until an editor fixes
  its values, but it cannot invalidate an existing published snapshot;
- relations, localization, rich HTML, deletion and scheduled publishing are
  explicitly deferred and require separate contracts and migration policies.
