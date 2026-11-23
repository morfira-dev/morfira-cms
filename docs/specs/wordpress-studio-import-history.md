# WordPress import history in Admin Studio

## Status

Implemented

## Problem and goals

Owners need to review the result of a migration batch after leaving the importer. Admin Studio lists completed WXR and public REST imports and can reopen or download their reports.

## Actors and permissions

An owner can list completed runs and retrieve their reports. Both reads use the existing owner session boundary and private, non-cacheable responses.

## User and system flows

1. The owner previews a WordPress source and applies the whole source or a selection.
2. A successful apply records its result and makes the run available in history.
3. The owner browses runs newest first, filters by source key, input format or scope, opens a saved report and can download its JSON.
4. The owner can return to the current import without losing its preview or selection.
5. The owner can open a newly created page or entry from the live or saved report in a separate tab. The editor opens the referenced draft on direct entry or reload.
6. From a live result the owner can preview the current source again with the same settings, or open the run history filtered to that source key. A fresh preview reads the source again and replaces the old report and selection before any apply is available.
7. From a saved report the owner can start a fresh preview for its source. Studio opens the importer with the saved input format and source key. The owner supplies the WXR file or REST URL and initiates the preview explicitly. Continuing from history replaces the current form and clears any current report and selection. The usual Back to import action still preserves the current form and report.

## Contracts

`GET /api/v1/admin/imports/wordpress/runs?page=1` returns `items`, `page` and `hasMore`. Each summary includes a run UUID, source key, input format, completion timestamp, scope and material outcome counts. Page size is 20. See the [history filter specification](wordpress-studio-history-filters.md).

`GET /api/v1/admin/imports/wordpress/runs/{id}` returns the saved `WordPressImportReport`. Report reads have the same shape as the apply response.

Newly imported rows can have a `draft` reference. Page references carry a draft UUID, and entry references also carry a content type UUID. Studio uses `/admin/pages?draft=<uuid>` and `/admin/entries?draft=<uuid>&type=<uuid>` to open the normal editors. The editor uses current authoring data and its existing access checks.

## Data and invariants

Each successful Studio apply is a run. A replay can be a distinct run with already-imported outcomes while the material ledger still prevents duplicate drafts. The report and corresponding content changes commit together. Dry-runs, unsuccessful requests and CLI imports have no history records. Reports are immutable and represent the outcome at the time of the request.

## Failure modes and privacy

An invalid page is rejected. An unknown run returns 404. A failed write rolls back the Studio apply. The report contains the existing bounded metadata and omits the source file, filename, credentials and actor identity.

Older reports and rows without a newly created draft remain readable without a link. If a linked draft is unavailable or the entry no longer matches the supplied content type, Studio displays an error in the corresponding workspace.

Continuing from history uses only the run summary's input format and source key. Studio starts with a blank endpoint or file and with public image downloads and CDN hosts cleared. The flow adds no fields to persisted reports, request parameters, or browser storage. Review from history and live-result shortcuts issue no apply requests. Existing ledger claims continue to determine which drafts remain eligible in the fresh preview.

## Acceptance criteria

- [x] History survives reload and supports paging and reopening a saved report.
- [x] Downloads reproduce the original applied report and changing views preserves the current import state.
- [x] Only owners can read history. Dry-runs and failed imports create no entries.
- [x] A failed run-history write rolls back content changes.
- [x] A newly imported page or entry opens in the existing editor from the report. Direct entry and reload preserve the target.
- [x] Dry-run and replay rows omit draft references. Malformed or stale links keep the workspace available.
- [x] Live results can preview the same source again and open source-filtered history. Historical WXR and REST runs start a fresh form with the recorded format and key. A new preview is required before another apply. The [Studio regression](../../front/e2e/wordpress-resume-import.spec.ts) covers request boundaries in Vite, and the [production journey](../../front/e2e/critical-journey.spec.ts) verifies WXR continuation and replay against MariaDB.

## Rollout and verification

An additive database migration creates the run table. Existing uploads and ledger claims continue to work. Earlier import runs have no stored report. Verify API authorization and rollback, report fidelity, pagination and a Chromium journey on Docker MariaDB. Run the draft-link Chromium regression against Vite with Strict Mode enabled to verify direct entry and request counts. See [ADR 0036](../adr/0036-wordpress-import-run-history.md).
