# Safe content deletion

## Goal

Editors can permanently remove mutable page and entry drafts without leaving public routes, scheduled jobs, taxonomy assignments or dangling entry relations.

## Contract

- `DELETE /api/v1/admin/pages/{id}` deletes a page draft and returns `204`.
- `DELETE /api/v1/admin/entries/{id}` deletes an entry draft and returns `204`.
- A missing draft returns `404`; an invalid identifier returns `422`.
- Entry deletion returns `409` while another draft references the entry.
- Published snapshots are retained as immutable audit records.

## Page lifecycle

Deletion atomically removes the active route, redirect history, publication schedule, draft and draft revisions. Revision removal is enforced by the existing database cascade.

## Entry lifecycle

Mutable single-entry relations are indexed in `content_entry_relations`. The target foreign key uses `ON DELETE RESTRICT`, making the application check race-safe. Deletion atomically removes the active route, publication schedule, taxonomy assignments, outgoing relation index rows, draft and revisions.

Self-references are outgoing rows and are removed with the source. References from other entries block deletion until editors update those entries.

## Acceptance criteria

1. Deleting published content removes its public route in the same transaction.
2. Deleting scheduled content removes the obsolete schedule.
3. Entry relations are indexed whenever an entry is created or updated.
4. Updating a content-type schema rebuilds relation indexes for its drafts.
5. Existing relations are backfilled during migration.
6. Incoming relations produce `409` and leave all state unchanged.
7. Immutable published snapshots are never deleted.
