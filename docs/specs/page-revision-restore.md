# Page revision restore

## Status
Implemented

## Problem
Authors cannot recover a page after an unwanted edit even though immutable revisions exist.

## Goals
- Restore any retained revision as a new current draft revision.
- Preserve optimistic locking and the full audit trail.

## Non-goals
- Repointing a public route, automatic publishing or deleting later revisions.

## Actors and permissions
- Authenticated editors and owners can restore revisions.

## User and system flows
1. The editor chooses a historical revision and confirms restoration.
2. The client submits the current draft version.
3. The backend copies historical fields into a newly appended revision and returns the new head.

## Contracts
- `POST /api/v1/admin/pages/{id}/revisions/{revision}/restore` accepts `RestorePageRevisionRequest`.

## Data and invariants
- Restore never updates or deletes a historical row.
- The new revision number is current version plus one.
- The current version in the request must match the stored draft.

## Failure modes
- Missing page or revision returns `404`; stale current version or slug collision returns `409`.

## Security and privacy
- The mutation is session authenticated and CSRF protected.

## Acceptance criteria
- [x] Restoring revision N creates revision current+1 with N's title, slug, blocks and SEO.
- [x] Public snapshots and routes remain unchanged.
- [x] A stale restore is rejected without partial writes.

## Verification plan
- Domain/application tests, persistence transaction coverage, HTTP tests and frontend interaction/API tests.

## Rollout and migration
- No migration. Existing histories are immediately restorable.
