# WordPress migration sources

## Status

Implemented

## Problem and goals

Owners managing several migrations need to find each WordPress installation and its latest saved batch without scanning a mixed list of runs. Studio provides a source overview alongside run history.

## Actors and flow

1. An owner opens Import history and switches from runs to sources.
2. The owner can search by source-key prefix. Results show recorded batch counts split between Studio and CLI, and the latest batch's time and outcomes.
3. The owner can open the latest report directly, or open all runs filtered to the exact source key. The report retains its existing download, draft-link and continuation actions. Returning from a latest report restores the source search and page.

## API and data

`GET /api/v1/admin/imports/wordpress/sources` accepts optional `page` (1–1,000,000, default 1) and `prefix` (up to 80 lowercase ASCII letters, digits, and single hyphen separators; a trailing hyphen is accepted). Omitted prefix returns all sources. Invalid values return 422. The private, owner-only response contains `items`, `page` and `hasMore`. Each item includes `source`, `runCount`, `studioRuns`, `cliRuns` and `latestRun` with the existing run summary shape.

Group the existing run summaries by exact source key, and sort sources by descending latest completion time, then ascending source key. Use descending completion time and run ID to select a source's latest run. Apply the filter before paging, with up to 20 sources per page. Counts describe recorded batches. Replays contribute to the batch count, and transient previews and unrecorded imports are absent. A source-key and completion index supports the read. Reports and ledger claims retain their existing shape and lifecycle.

## Failure modes and privacy

Unauthenticated and non-owner reads retain the existing history access rules. Studio distinguishes loading, retryable errors, an empty history, and a valid search with no results. The overview uses summary columns and does not read the saved report body until an owner opens it. It sends no WordPress requests and exposes no new source configuration.

## Acceptance criteria

- [x] CLI and Studio runs with the same source key share one row, including accurate channel counts and deterministic latest-run selection.
- [x] Search, paging, and role checks operate server-side. Existing reports and history filters continue to work.
- [x] Owners can open the latest report and return to the active source list, or open all runs for one source. These actions preserve the import form.

## Verification and rollout

Run the backend and frontend gates, regenerate OpenAPI client types, and check HTTP authorization and validation. Use MariaDB to verify grouped counts, latest-run selection, filtering, index migration and pagination. Exercise both navigation paths and request counts in Chromium against Vite with Strict Mode enabled. The additive index does not change persisted reports.

Coverage includes the [CLI integration journey](../../scripts/test-wordpress-import.sh) and the [Studio browser scenario](../../front/e2e/wordpress-migration-sources.spec.ts).
