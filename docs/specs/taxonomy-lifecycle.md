# Taxonomy lifecycle

## Status

Implemented

## Problem

Editors can create taxonomies and terms, but without lifecycle operations they cannot correct labels, reorganize hierarchy or remove obsolete authoring data. Those changes must not rewrite taxonomy descriptors already embedded in published entry snapshots.

## Goals

- Let authenticated editors update and delete mutable taxonomies and terms.
- Preserve valid, acyclic hierarchy and slug uniqueness.
- Keep previously published entry snapshots immutable.

## Non-goals

- Rewriting historical taxonomy descriptors.
- Bulk term merges, automatic redirects or taxonomy localization.
- Assigning taxonomies to pages.

## Actors and permissions

- Authenticated editors can manage taxonomies and terms through protected admin endpoints.
- Anonymous callers are denied by the existing admin firewall.
- Every mutation requires the existing session-bound CSRF token.

## User and system flows

1. An editor selects a taxonomy or term in Admin Studio.
2. The editor updates its fields or confirms deletion.
3. The application validates lifecycle and hierarchy invariants.
4. Persistence changes mutable authoring records and assignments.
5. Admin Studio replaces its local representation with the returned canonical resource.

## Contracts

- `PUT /api/v1/admin/taxonomies/{id}` updates name, slug and hierarchy mode.
- `DELETE /api/v1/admin/taxonomies/{id}` removes the mutable taxonomy.
- `PUT /api/v1/admin/taxonomies/{id}/terms/{termId}` updates a term and its optional parent.
- `DELETE /api/v1/admin/taxonomies/{id}/terms/{termId}` removes a leaf term and returns the updated taxonomy.
- Existing `/api/v1` response shapes remain backward compatible.

## Data and invariants

- A taxonomy slug is globally unique.
- A term slug is unique within its taxonomy.
- Flat taxonomies cannot contain parent links.
- Parent terms belong to the same taxonomy, and hierarchy remains acyclic.
- A non-leaf term cannot be deleted.
- Taxonomy and term deletion cascades through mutable draft assignments only.
- Published snapshot descriptors are never updated or deleted by this lifecycle.

## Failure modes

- Invalid identifiers or payloads return `422`.
- Missing taxonomies or terms return `404`.
- Slug collisions and prohibited hierarchy transitions return `409`.
- Failed validation leaves authoring state unchanged.

## Security and privacy

- Inputs are allow-listed and validated at the HTTP and domain boundaries.
- Mutations retain session authentication, editor authorization and CSRF enforcement.
- Taxonomy data contains no new sensitive fields.

## Acceptance criteria

- [x] Editors can update taxonomy and term fields through Admin Studio.
- [x] Editors can delete taxonomies and leaf terms.
- [x] Cycles, cross-taxonomy parents, non-leaf deletion and invalid flattening are rejected.
- [x] Published snapshots keep their original taxonomy descriptors.
- [x] OpenAPI and generated frontend types describe every new operation.

## Verification plan

- PHPUnit application tests cover update, deletion and hierarchy conflicts.
- Frontend API tests cover authenticated update and deletion requests.
- PHPStan, OpenAPI type generation, TypeScript, Vitest and production build pass.
- Browser inspection confirms lifecycle controls render in Admin Studio.

## Rollout and migration

No schema migration is required because existing taxonomy tables already support update and cascading deletion. Rollback removes the new endpoints and controls without changing stored data. Published snapshots remain compatible in either direction.
