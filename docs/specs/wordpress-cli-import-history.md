# WordPress CLI import history

## Status

Implemented

## Problem and goals

Owners use the command line for imports that exceed the limits of a Studio request. A CLI operator can opt into a saved report so an owner can review the batch and open its drafts in Studio.

## Actors and flow

1. The CLI operator runs a WXR or public REST dry-run with the normal options.
2. The operator applies the same source with `--apply --record-history`.
3. An owner opens Import history, filters by execution channel if needed, and opens the saved report and its new drafts. A run may be combined with other filters, including source key.

## Contracts and invariants

The new flag requires `--apply` and rejects `--include-private`. Both checks occur before reading source files or making HTTP requests. Existing commands retain their output and their default history behavior. A recorded apply writes the same report to stdout and history, with bounded titles for ordinary materials and draft references for newly created drafts. Studio and CLI history use the same report shape. The run summary has `channel: studio | cli`; `GET /api/v1/admin/imports/wordpress/runs?channel=cli` combines the channel with existing filters before pagination. Existing rows have the `studio` channel.

## Privacy and failure handling

History remains owner-only and stores report metadata without credentials, source configuration, filesystem paths, restricted titles, or raw WordPress content. A failed write rolls back the recorded import and its ledger claims. Replays can append a new immutable report. The existing ledger keeps drafts unique. Private REST imports retain their CLI-only reports.

## Acceptance criteria and verification

- [x] WXR and public REST apply can opt into a saved report, and default CLI applies and dry-runs keep their current history behavior.
- [x] Incompatible flags fail before source access. Recorded reports contain only safe metadata and current draft links.
- [x] History identifies CLI and Studio runs and filters channel together with the existing criteria. Existing history is readable after migration.
- [x] A failed history write rolls back the import, and a replay keeps exactly one draft for each material.

Run backend application and architecture checks, regenerate the OpenAPI client types, run the Studio gate, and exercise both the production CLI fixture and the history view in Chromium.
