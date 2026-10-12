# ADR 0009: Versioned immutable navigation snapshots

## Status

Accepted.

## Decision

Navigation authoring and public delivery use separate models. Editors change a
versioned navigation draft identified by a stable UUID and human-readable code.
Every update supplies the current integer version; a stale write fails with an
optimistic-lock conflict instead of overwriting another editor's work.

A navigation item tree is ordered, contains at most 100 items in total, and has at
most three levels including the root level. Each item has a stable UUID, label,
destination, new-tab flag and ordered children. Destinations are limited to local
root-relative paths or absolute HTTP(S) URLs. This is a syntactic safety boundary;
the CMS does not promise that an external destination exists or is trustworthy.

Publishing copies one exact draft version into an immutable snapshot and atomically
makes that snapshot active for its code. The `(navigation_id, source_version)` pair
is unique, so publishing the same version is idempotent. Later draft edits do not
change an already published menu, and public delivery never reads navigation drafts.

The public endpoint resolves the active snapshot by code. Its snapshot identifier
is returned as the ETag. A matching `If-None-Match` returns `304 Not Modified`
without a response body. Replacing an active snapshot changes the ETag; mutating a
snapshot in place is forbidden.

## Consequences

- concurrent authoring changes fail explicitly rather than silently losing data;
- public navigation is deterministic, cacheable and isolated from incomplete edits;
- rollback can later move the active code pointer to an older snapshot without
  reconstructing the menu;
- draft and snapshot storage duplicate item trees, which is accepted for reliable
  delivery and auditability;
- changing a navigation code requires collision checks for both authoring and active
  public routes;
- theme placement, localization and permission-aware menu variants remain separate
  future concerns rather than fields in this first contract.
