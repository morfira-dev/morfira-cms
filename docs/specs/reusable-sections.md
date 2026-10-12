# Reusable sections

## Status
Implemented

## Problem
Editors must recreate common block compositions for every page.

## Goals
- Let editors create named, versioned reusable sections from valid page blocks.
- Insert a section into a page as independent block copies with fresh identifiers.
- Preserve deterministic revisions and immutable publication snapshots.

## Non-goals
- Live references that silently rewrite published pages or cross-installation synchronization.

## Actors and permissions
- Editors list, create, revise and delete sections and insert them into drafts.

## User and system flows
1. An editor saves a selected block composition as a section.
2. The palette lists available sections.
3. Inserting copies the latest section blocks with new IDs into the current draft.
4. Later section changes do not mutate existing page drafts or snapshots.

## Contracts
- `/api/v1/admin/sections` supports list and create.
- `/api/v1/admin/sections/{id}` supports update and delete with optimistic locking.

## Data and invariants
- Names are non-empty and unique; blocks use the existing validated page block schema.
- A section version increases on every revision.

## Failure modes
- Stale updates return `409`; missing sections return `404`; invalid blocks return `422`.

## Security and privacy
- Sections are authenticated authoring data and never public endpoints by themselves.

## Acceptance criteria
- [x] Editors can create, update, list and delete versioned sections.
- [x] Insertion creates independent blocks with fresh identifiers.
- [x] Existing pages and published snapshots never change when a section changes.

## Verification plan
- Domain, persistence, HTTP, OpenAPI, frontend transformation and build tests.

## Rollout and migration
- Additive section tables; existing content is unchanged.
