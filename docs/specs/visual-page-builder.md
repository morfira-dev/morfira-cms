# Visual page builder

## Status
Implemented

## Problem
The page editor exposes block forms but lacks direct reordering, selection, visual hierarchy and reusable composition actions.

## Goals
- Provide a block palette, visual canvas, inspector and deterministic drag-and-drop ordering.
- Support keyboard-accessible move, duplicate and remove actions.
- Show a responsive preview representation while retaining the existing block contract.

## Non-goals
- Free-form pixel positioning, arbitrary HTML, nested HTTP components or collaborative cursors.

## Actors and permissions
- Authenticated editors manipulate the current mutable draft only.

## User and system flows
1. The editor adds a block or reusable section to the canvas.
2. Selecting a block opens its inspector.
3. Dragging or move actions change only the local order until the revision is saved.
4. Preview and publication continue to use the server-owned block contract.

## Contracts
- No new public persistence shape is required for ordering; the ordered block list remains authoritative.

## Data and invariants
- Block identifiers remain stable while moving and are regenerated when duplicating.
- Every action marks the draft dirty and preserves optimistic locking.

## Failure modes
- Unsupported drops are ignored; media blocks still require an available asset.

## Security and privacy
- The builder never executes stored HTML or extension code in Admin Studio.

## Acceptance criteria
- [x] Blocks can be selected, moved, dragged, duplicated and removed.
- [x] Empty, selected and dirty states remain visible and keyboard operable.
- [x] Saved ordering round-trips through revisions and publication unchanged.

## Verification plan
- Pure transformation tests, frontend checks and a browser editing journey.

## Rollout and migration
- Existing ordered block data requires no migration.
