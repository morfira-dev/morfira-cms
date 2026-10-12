# ADR 0017: Build taxonomy archives from immutable snapshots

- Status: Accepted
- Date: 2026-10-12

## Context

Public taxonomy archives must stay consistent with headless delivery and must not expose drafts. Joining live authoring taxonomies during delivery would allow an editor rename or deletion to change already published output.

## Decision

Use the existing published-entry collection query and its snapshot taxonomy index for both filtering and server-rendered archive pages. Resolve the archive label from a matching immutable descriptor instead of the mutable taxonomy tables.

The web route owns presentation, cache headers and pagination. The versioned headless endpoint remains the machine-readable archive contract through its existing query parameters.

## Alternatives and constraints

- Joining mutable taxonomy tables at read time was rejected because published output would change without republishing.
- Creating a second archive-specific snapshot was rejected because entry snapshots already contain the required descriptors and index.
- Offset pagination is accepted for the first slice because archives are bounded to immutable active snapshots and the contract can evolve independently from storage.

## Consequences

- Web and headless consumers filter the same published snapshot set.
- Archive output stays deterministic across mutable taxonomy changes.
- Historical labels can coexist until affected entries are republished.
- Archive pages currently use fixed-size offset pagination; cursor pagination can be introduced behind a future contract.

## Migration impact

No database migration or snapshot backfill is required. Existing snapshot term indexes serve the new route.

## Verification

Presentation tests cover matching, empty, invalid and out-of-range requests. Router inspection and backend analysis verify registration and service wiring.
