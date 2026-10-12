# ADR 0004: Publish immutable delivery snapshots

## Status

Accepted.

## Decision

Publishing never exposes or mutates a draft in place. It appends an immutable snapshot of one exact draft revision and atomically moves the page's active public route to that snapshot.

The `(page_id, source_revision)` pair is unique, making repeated publication of the same revision idempotent. Drafts may continue changing after publication without changing public HTML or the headless delivery API.

Public HTML and `/api/v1/content` read only the active snapshot. Snapshot identifiers are used as ETags. Slugs remain globally unique among active public routes.

## Consequences

- an incomplete draft can never leak through delivery reads;
- rollback can later be implemented by moving the route pointer to an older snapshot;
- public reads avoid joins against mutable authoring tables;
- snapshots consume additional storage, which is accepted for auditability and predictable delivery;
- publishing and route activation must happen in one database transaction.
