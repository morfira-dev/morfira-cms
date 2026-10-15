# ADR 0015: Restrict deletion of referenced entries

- Status: Accepted
- Date: 2026-10-12

## Context

Entry values are stored as JSON for schema flexibility. Relation values therefore cannot rely on a foreign key inside that JSON. An application-only preflight check would still allow a concurrent update to create a dangling relation during deletion.

## Decision

Maintain a normalized `content_entry_relations` index alongside mutable entry JSON. Each row identifies the source entry, field key, zero-based position and target entry. Source deletion cascades; target deletion is restricted.

Draft deletion is orchestrated by a dedicated gateway using one database transaction across draft-owned lifecycle records. Published snapshots are intentionally outside that transaction and remain immutable.

## Consequences

- Referential integrity is enforced by MariaDB, including concurrent writes.
- Relation index updates must remain in the same transaction as entry writes.
- Content-type schema updates rebuild indexes for drafts of that type in the same transaction.
- Existing JSON relations require migration backfill.
- Single and multiple relations share one ordered index; existing single relations use position `0`.
