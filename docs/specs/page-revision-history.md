# Page revision history

## Status
Implemented

## Problem
Morfira stores immutable page revisions but authors cannot inspect them.

## Goals
- List a page's immutable revisions newest first with complete restorable content.
- Make the history available inside the page editor.

## Non-goals
- Visual diffs, author attribution and pagination.

## Actors and permissions
- Authenticated editors and owners can inspect page revisions.

## User and system flows
1. Opening a page loads its current draft and revision history.
2. The editor can inspect revision number, timestamp and title without modifying state.

## Contracts
- `GET /api/v1/admin/pages/{id}/revisions` returns `PageRevisionCollection`.

## Data and invariants
- Revisions remain append-only and are ordered by revision number descending.

## Failure modes
- An unknown page returns `404`; malformed identifiers return `422`.

## Security and privacy
- Draft history remains behind `ROLE_EDITOR` and is never cacheable public content.

## Acceptance criteria
- [x] Every saved revision is returned once in descending order.
- [x] The editor renders an empty-safe history list.
- [x] Reading history never mutates the page.

## Verification plan
- Repository/application tests, HTTP contract tests, frontend API tests and build.

## Rollout and migration
- No schema migration; existing revision rows become readable.
