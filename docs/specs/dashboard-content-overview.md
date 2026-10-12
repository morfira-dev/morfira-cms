# Dashboard content overview

## Status
Implemented

## Problem
The dashboard contains marketing copy but gives editors no useful view of their installation.

## Goals
- Show live totals for pages, media, content types and taxonomies.
- Provide direct navigation to the corresponding workspaces.

## Non-goals
- Analytics, publication trends, storage usage and cross-module backend reporting.

## Actors and permissions
- Authenticated editors and owners see the same content overview.

## User and system flows
1. Opening Dashboard requests existing module-owned collection endpoints in parallel.
2. Cards show totals or a recoverable error without blocking navigation.
3. A card action navigates through the existing History API router.

## Contracts
- No new backend contract; existing collection endpoints remain module owned.

## Data and invariants
- Counts are computed client-side from already authorized collection responses.

## Failure modes
- Partial request failure produces an error state and retry action; no guessed counts are shown.

## Security and privacy
- Existing server-side endpoint authorization remains authoritative.

## Acceptance criteria
- [x] Four live totals load in parallel.
- [x] Loading, success and error states are visible.
- [x] Card actions navigate without a full page reload.

## Verification plan
- Frontend API/component tests, type checking and production build.

## Rollout and migration
- No migration or compatibility impact.
