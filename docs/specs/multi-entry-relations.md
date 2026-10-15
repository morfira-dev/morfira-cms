# Multi-entry relations

## Status

Implemented

## Problem

Structured content can currently reference only one target entry per relation field. Common CMS models such as article authors, related products and curated collections require an ordered set of references without duplicating schema fields.

## Goals

- Let relation fields opt into multiple ordered target entries.
- Validate every target through the existing schema-to-content lookup port.
- Preserve relation order in drafts, revisions, published snapshots, SSR and headless responses.
- Keep the normalized relation index race-safe for deletion checks.
- Let Admin Studio editors select and reorder multiple target entries.

## Non-goals

- Reverse-relation editing or graph queries.
- Embedding mutable target content in published snapshots.
- Automatic republishing when a target entry changes.
- Relations across installations or external APIs.

## Actors and permissions

- Owners configure whether a relation field accepts one or multiple targets.
- Authenticated editors select targets while editing entries.
- Anonymous clients read stable target identifiers from immutable published snapshots.

## User and system flows

1. An owner creates or updates a relation field and enables multiple values.
2. Admin Studio renders a multi-select backed by entries of the configured target type.
3. Create and update validate the ordered UUID list, reject duplicates and cap it at 100 targets.
4. Persistence stores the list in entry JSON and writes one ordered relation-index row per target in the same transaction.
5. Publication copies the validated ordered list into an immutable snapshot.
6. Complete-CMS delivery renders the referenced entry identifiers as an ordered list; headless delivery returns the UUID array.

## Contracts

- `ContentField.multiple` is an optional boolean and is valid only for `relation` fields; omission means `false` for compatibility.
- A single relation value remains `string | null`.
- A multiple relation value is a list of 1 to 100 unique UUID strings, or `null`/an empty list when optional.
- Existing `/api/v1` routes and single-relation payloads remain compatible.

## Data and invariants

- Relation order is part of authored content and is preserved by an explicit zero-based position in `content_entry_relations`.
- The relation index has one row per source field position and prevents the same target appearing twice in one field.
- Target foreign keys continue to use `ON DELETE RESTRICT`; source deletion continues to cascade.
- Published snapshots remain immutable and keep identifiers rather than resolving mutable target drafts.

## Failure modes

- Non-list multiple values, malformed UUIDs, duplicates, missing targets, wrong target types or more than 100 targets return `422`.
- Required multiple relations reject `null`, missing and empty lists.
- A failed index write rolls back the entry and revision write.
- Existing schemas without `multiple` continue to behave as single relations.

## Security and privacy

- Target existence and type are validated server-side; browser options are not trusted.
- The 100-target cap bounds request, validation and index-write cost.
- Public delivery exposes only identifiers deliberately included in an immutable published snapshot.

## Acceptance criteria

- [x] Existing single relation fields remain compatible.
- [x] Owners can enable multiple values for relation fields through the API and Admin Studio.
- [x] Editors can save an ordered unique set of up to 100 valid targets.
- [x] Invalid, duplicated, wrong-type and empty required sets are rejected.
- [x] Relation index rows preserve order and continue to block unsafe target deletion.
- [x] Published headless and complete-CMS delivery preserve the ordered identifier list.
- [x] Migration, backend, frontend, generated-contract and production browser gates pass.

## Verification plan

- Domain/application tests cover compatibility, validation, duplicates, limits and required semantics.
- Persistence tests and migration execution cover ordered index rows and foreign-key behavior.
- Publishing and presentation tests cover immutable arrays in headless and SSR delivery.
- Frontend tests cover initialization and validation; production E2E authors and publishes a multiple relation.
- Run backend and frontend full checks, migration status, production E2E and `git diff --check`.

## Rollout and migration

An additive migration adds `position` to the existing relation index and expands its primary key. Existing single-relation rows receive position `0`. Rollback is safe only after every multiple relation has been converted to a single target because the old primary key permits one row per source field.
