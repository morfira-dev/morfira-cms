# Review and refresh linked section usages

## Status
Implemented

## Problem
Editors need to find the pages that use a library section and update multiple copies after reviewing the new source version.

## Goals
- List draft page usages with locale, draft revision, and each linked placement version.
- Let editors choose pages and review the scope before creating new draft revisions.
- Report outcomes independently for selected pages and leave published snapshots unchanged.

## Actors and permissions
Editors use the existing authenticated admin API and CSRF protection for refresh. The page authoring module owns the operation.

## User and system flows
1. An editor opens section usages in Studio and reviews the library version, affected pages and their current placement versions.
2. The editor selects up to 50 outdated pages and applies the reviewed version.
3. The server checks source and page versions and creates a new revision for each selected page that can be updated. Studio shows individual results and lets the editor reload current usages to review conflicts.

Studio leaves the currently open draft under the existing single-page editing flow, where unsaved edits remain visible and the editor decides whether to apply the new section version.

## Contracts
`GET /api/v1/admin/sections/{id}/usage` returns the current library section, paginated matching page usages, and the total count. `POST /api/v1/admin/sections/{id}/refresh` accepts the reviewed source version and selected page IDs with expected draft versions.

## Data and invariants
A successful page refresh replaces only outdated placements of the selected source and preserves all unrelated blocks and metadata. Each page is committed through its existing optimistic revision save. The batch pins the reviewed source object at the start. Public HTML and API use their existing immutable snapshot until each page is published.

## Failure modes
A missing source returns 404. A changed source returns 409 before any page writes. Invalid input returns 422. Outcomes for individual pages distinguish updated, current, conflict, missing, and unlinked. Retrying a completed request cannot create duplicate revisions.

## Security and privacy
Page usages and the batch report are authoring data. The refresh accepts page IDs and revision numbers. The server obtains block content from the selected section version and validates materialized blocks through page authoring.

## Acceptance criteria
- [x] Editors can find matching draft usages and select a subset after reviewing the target version.
- [x] Selected outdated pages get one new revision. Unselected pages and published content remain unchanged.
- [x] Stale source and page versions are detected. A page-level conflict does not prevent other selected pages from updating.

## Verification plan
The [application test](../../back/tests/Content/Application/RefreshSectionPlacementsTest.php) checks stale source rejection, independent page outcomes, multiple placements, sanitization, and preserved revision history. The [production browser journey](../../front/e2e/critical-journey.spec.ts) checks usage selection, a concurrent write, and the published HTML and headless snapshots before and after explicit publication.

## Rollout and migration
Uses the existing linked placement metadata and page revision model. Existing single-page refresh remains available.
