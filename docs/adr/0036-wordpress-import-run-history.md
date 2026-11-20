# ADR 0036: Store completed WordPress import reports with their drafts

## Status

Accepted

## Context

The Studio report can describe thousands of source records and is tied to the result of a specific apply. The item ledger stores identity and replay protection.

## Decision

Store each successful Studio WXR apply as a run with summary fields and its immutable report. Commit it in the same database transaction as the draft changes. Return summaries from the collection endpoint and load report data by run identity when requested. Use the existing owner authorization boundary.

## Alternatives and consequences

Reconstructing reports from current drafts would change historical outcomes after edits. Storing reports in the browser would tie history to one session and device. The database stores bounded report metadata alongside the site backup. Successful repeats record administrative activity while ledger claims continue to govern draft creation.

## Migration and verification

The table is additive and starts empty. Verify atomic writes on failure, isolated owner access, summary paging and historical report retrieval on MariaDB.
