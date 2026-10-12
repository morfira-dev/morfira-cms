# ADR 0016: Mutable taxonomy lifecycle outside publication snapshots

- Status: Accepted
- Date: 2026-10-12

## Context

Editors need to correct taxonomy labels and hierarchy, but public responses must remain deterministic. Published entries already copy compact taxonomy descriptors into immutable snapshots.

## Decision

Treat taxonomies, terms and draft assignments as mutable authoring data. Updates and deletions affect only that authoring model. Published entry snapshots retain their embedded descriptors and are never rewritten.

Hierarchy changes are guarded in the application layer: flat taxonomies cannot have parent links, parent terms must belong to the same taxonomy, cycles are rejected and non-leaf terms cannot be deleted. Database uniqueness and foreign keys remain the concurrency-safe integrity boundary.

## Alternatives and constraints

- Rewriting published snapshots was rejected because it breaks deterministic delivery.
- Soft deletion was deferred because no restoration, audit or visibility contract exists yet.
- Unrestricted parent deletion was rejected because silently promoting children changes editorial meaning.

## Consequences

- Editorial corrections apply to future publications without altering old snapshots.
- Deleting a taxonomy can leave its historical descriptors visible in published content.
- Reorganizing a hierarchy is explicit and cannot silently orphan children.
- Republish is required for public snapshots to adopt current taxonomy metadata.

## Migration impact

Existing tables and snapshot formats are unchanged. The capability is additive at the application and HTTP layers.

## Verification

Application tests cover valid lifecycle operations and blocked hierarchy transitions. OpenAPI generation, frontend request tests, PHPStan and full builds verify the complete boundary.
