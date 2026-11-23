# WordPress import history filters

## Status

Implemented

## Problem and goals

Owners need to find a completed migration batch by source key, input format, import scope or execution channel across the entire history. The list shows both the input format and whether the batch was run from Studio or the CLI.

## Actors and permissions

The existing owner-only, private history surface serves all filtered requests.

## User and system flows

An owner can enter an exact source key and choose WXR or REST, full source or selection, and Studio or CLI. Applying or clearing filters starts on page one. Opening an individual report and returning to the list restores its filters and page. A valid filter combination with no matches displays a distinct empty state.

## Contracts

The list endpoint accepts optional `source`, `input`, `scope` and `channel` query parameters. The source uses the same identifier rules as import. Input values are `wxr` and `rest`, scope values are `all` and `selection`, and channel values are `studio` and `cli`. Filters combine with AND before newest-first pagination. Invalid filter values return 422. Summaries include `input` and `channel`. Saved reports retain their original shape and contents.

## Data and invariants

Store the input format with the other run summary fields. Existing WXR rows keep the WXR default and already recorded REST reports are backfilled from their immutable JSON. Subsequent records write the report format in the same transaction as the report and draft changes.

## Acceptance criteria

- [x] Existing history requests return all formats in stable newest-first order. Existing reports remain readable.
- [x] Exact source, input and scope filters compose before pagination. Bad filter types or values return 422.
- [x] Studio shows the format, supports apply and clear, distinguishes filtered empty results, and preserves the applied filters and page when returning from a report.
- [x] MariaDB migration preserves prior WXR and REST runs, and both input formats remain available in history after new imports.

## Verification and rollout

Run the backend and frontend gates and verify the filter flow in Chromium. Apply the additive migration to MariaDB with pre-existing history for both formats. The migration can drop the summary column on rollback because the report retains its original input.
