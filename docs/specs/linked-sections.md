# Linked reusable sections

## Status
Implemented

## Problem
Editors repeat shared compositions across pages and need a way to apply a new section version without rebuilding those pages.

## Goals
- Insert an independent copy or a linked placement in the page builder.
- Update a section from the canvas and explicitly refresh or detach a linked placement.
- Keep draft history and published HTML and API snapshots deterministic.

## Non-goals
- Automatic republishing and nested section references. See [bulk section refresh](bulk-section-refresh.md) for the separate multi-page flow.

## Actors and permissions
Editors use the existing authenticated, CSRF-protected page and section endpoints.

## User and system flows
1. An editor inserts a linked placement from the section library. The page gets concrete blocks and remembers the section ID, source version, and member block IDs.
2. An editor updates the library section using its optimistic lock. Other pages can fetch and apply the new version to their current draft.
3. Studio loads the current catalog and shows a block summary for the draft placement and the available library version. Applying the reviewed version replaces the placement's blocks at the position of its first remaining member. The editor saves a revision and publishes when ready.
4. Detaching keeps the concrete blocks. Removing a member dissolves the link and keeps the other blocks.

## Contracts
Admin page and revision payloads may contain `sectionLinks`. Omitting this field on an update retains links whose blocks still exist. Sending an empty list detaches all placements. Public HTML and public API continue to use concrete published blocks.

## Data and invariants
A placement refers to one section version and one or more distinct existing block IDs. Each referenced ID identifies exactly one block on the page. A block belongs to at most one placement. Draft and revision links are stored with their corresponding blocks. A missing library section leaves the stored content available for editing and publishing.

## Failure modes
Invalid placements return HTTP 422. Stale page or section writes use the existing HTTP 409 contracts. A deleted source cannot be refreshed. Nothing is written by opening or refreshing the editor until the draft is saved.

## Security and privacy
Placement metadata is authoring data. Public snapshots contain the same concrete block types as ordinary pages and use the existing rich-text sanitization and publication checks.

## Acceptance criteria
- [x] Linked insertion survives save and reload, and restoring a revision restores its metadata.
- [x] Refresh preserves unrelated blocks and requires the normal save and publish lifecycle.
- [x] Invalid or overlapping block membership is rejected and old payloads remain valid.

## Verification plan
The [HTTP tests](../../back/tests/Content/Presentation/Http/LinkedSectionsControllerTest.php) verify membership validation, older payloads, conflict handling, detaching, and revision restoration. The [builder tests](../../front/src/features/pages/pageBuilder.test.ts) cover replacement and block ordering. The [production Chromium journey](../../front/e2e/critical-journey.spec.ts) checks the saved placements across reloads and verifies publication behavior through HTML and the headless API.

## Rollout and migration
Nullable JSON columns are additive. Existing drafts and revisions read as having no linked placements.
