# Entry revision history and restore

## Status

Implemented

## Problem

Entry revisions are already written append-only, but editors cannot inspect or restore them. A mistaken structured-content edit therefore requires manual reconstruction even though the historical data exists.

## Goals

- Expose complete immutable entry revision history to authenticated editors.
- Restore a chosen revision by creating a new draft head rather than mutating history.
- Revalidate restored values against the current schema and rich-text trust boundary.
- Preserve optimistic locking and publication independence.

## Non-goals

- Restoring an old content-type schema.
- Deleting, editing or compacting historical revisions.
- Automatically publishing a restored revision.
- Visual field-by-field diffs.

## Actors and permissions

- Authenticated editors and owners can list and restore entry revisions.
- Anonymous users cannot access revision data.

## User and system flows

1. Opening a saved entry loads its revision history newest first.
2. The editor selects an older revision while the current draft is clean.
3. The backend verifies the expected current version, sanitizes and validates historical values against the current schema, and appends a new revision.
4. Admin Studio replaces the form with the new draft head; public delivery remains unchanged until explicit publication.

## Contracts

- `GET /api/v1/admin/entries/{id}/revisions` returns `EntryRevisionCollection`.
- `POST /api/v1/admin/entries/{id}/revisions/{revision}/restore` accepts the current optimistic `version` and returns `Entry`.
- Missing entries or revisions return `404`; stale versions return `409`; values incompatible with the current schema return `422`.

## Data and invariants

- `content_entry_revisions` remains append-only.
- Restore always creates `current version + 1`; historical rows never change.
- Publication snapshots and active routes are unaffected until explicit publication.
- Restored relation indexes are rebuilt transactionally by the entry repository.

## Failure modes

- Missing entry or revision returns `404`.
- A concurrent edit returns `409` without appending a revision.
- Historical values invalid under the current schema return `422` without changing the draft.

## Security and privacy

- Revision endpoints use existing authenticated admin protection and CSRF on restore.
- Rich text is sanitized again before restored values become mutable draft state.

## Acceptance criteria

- [x] Editors can inspect complete immutable entry history newest first.
- [x] Restoring an older revision appends a new validated draft version.
- [x] Stale, missing and schema-incompatible restores leave state unchanged.
- [x] Admin Studio disables restore while the form has unsaved changes.
- [x] Public delivery changes only after an explicit publish.
- [x] Backend, frontend, OpenAPI and production browser gates pass.

## Verification plan

- Repository/application tests cover ordering, append-only restore and optimistic conflict.
- Presentation tests cover list, restore and error translation.
- Frontend API/unit tests cover contracts and dirty-state rules.
- Production E2E restores an entry and verifies publication remains explicit.
- Run all affected repository gates.

## Rollout and migration

No migration is required because entry revisions are already persisted. Rollback removes the read/restore surfaces without changing stored revisions.
