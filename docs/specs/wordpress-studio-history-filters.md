# WordPress import history filters

## Status

Implemented

## Problem and goals

Owners need to find a completed migration batch by source key, input format or import scope across the entire history. The list should show the format alongside the existing summary.

## Actors and permissions

The existing owner-only, private history surface serves all filtered requests.

## User and system flows

An owner can enter an exact source key and choose WXR or REST and full source or selection. Applying or clearing filters starts on page one. Opening an individual report and returning to the list restores its filters and page. A valid filter combination with no matches displays a distinct empty state.

## Contracts

The existing list endpoint accepts optional `source`, `input` and `scope` query parameters. The source uses the same identifier rules as import. Input values are `wxr` and `rest`, and scope values are `all` and `selection`. Filters combine with AND before newest-first pagination. Invalid filter values return 422. Summaries include `input`. Saved reports retain their original shape and contents.

## Data and invariants

Store the input format with the other run summary fields. Existing WXR rows keep the WXR default and already recorded REST reports are backfilled from their immutable JSON. Subsequent records write the report format in the same transaction as the report and draft changes.

## Acceptance criteria

- [x] Existing history requests return all formats in stable newest-first order. Existing reports remain readable.
- [x] Exact source, input and scope filters compose before pagination. Bad filter types or values return 422.
- [x] Studio shows the format, supports apply and clear, distinguishes filtered empty results, and preserves the applied filters and page when returning from a report.
- [x] MariaDB migration preserves prior WXR and REST runs, and both input formats remain available in history after new imports.

## Verification and rollout

Run the backend and frontend gates and verify the filter flow in Chromium. Apply the additive migration to MariaDB with pre-existing history for both formats. The migration can drop the summary column on rollback because the report retains its original input.
