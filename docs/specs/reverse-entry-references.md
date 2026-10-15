# Reverse entry references

## Status

Implemented

## Problem

Deletion protection already knows which draft entries reference a target, but editors discover those dependencies only after a failed delete. Admin Studio needs a safe reverse-reference view and a direct path to edit the source entry.

## Goals

- Expose mutable incoming entry references through an authenticated admin API.
- Show source title, content type and field key in Admin Studio.
- Let an editor open the referring draft without constructing URLs manually.
- Reuse the normalized relation index that owns deletion integrity.

## Non-goals

- Public reverse graph APIs or published-snapshot backlinks.
- Inline mutation of another entry from the target form.
- Recursive graph traversal or relation counts across installations.

## Actors and permissions

- Authenticated editors and owners can inspect incoming mutable references.
- Anonymous users cannot access the endpoint.

## User and system flows

1. Opening a saved entry requests its incoming references.
2. The backend validates the target exists and reads the normalized relation index.
3. Admin Studio displays every source draft and relation field.
4. Selecting a reference switches to the source content type and opens that draft for editing.

## Contracts

- `GET /api/v1/admin/entries/{id}/references` returns an `EntryReferenceCollection`.
- Each reference contains `id`, `contentTypeId`, `title` and `fieldKey`.
- Missing entries return `404`; malformed identifiers return `422`.

## Data and invariants

- Results describe mutable drafts only and never infer state from immutable published snapshots.
- The same normalized index is used by deletion protection and reverse-reference reads.
- Self-references are omitted because source deletion removes its outgoing rows atomically.

## Failure modes

- Missing targets return `404`.
- Invalid identifiers return `422`.
- Database failures return the standard server error without changing content.

## Security and privacy

- The endpoint remains under existing session authentication and editor authorization.
- Responses contain editorial identifiers and titles only; no unpublished field values are exposed.

## Acceptance criteria

- [x] Saved entries expose all mutable incoming references through OpenAPI.
- [x] Single and multiple relation fields appear without duplicate target rows per source field.
- [x] Admin Studio displays references and opens the referring draft.
- [x] Missing and malformed targets are handled without leaking data.
- [x] Backend, frontend and production browser gates pass.

## Verification plan

- Application and presentation tests cover references, missing targets and malformed identifiers.
- Frontend API tests cover the request and response contract.
- Production E2E opens a source entry through a reverse reference.
- Run all affected repository gates.

## Rollout and migration

No additional migration is required beyond the ordered relation-index migration. Rollback removes the read endpoint and UI without changing stored content.
