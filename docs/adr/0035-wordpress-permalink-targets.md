# ADR 0035: Record WordPress permalink targets in the import ledger

## Status

Accepted

## Context

WordPress migrations can span several requests or source files. The item ledger prevents duplicates. Its earlier records for pages and posts hold the WordPress identity without its Morfira draft identity.

## Decision

Store the supported public source permalink, draft kind and draft ID on a newly claimed content item in the existing import transaction. Scope lookup by the operator's source key and require the same WordPress host and port used for in-batch matching. Resolve historical targets through the current authoring repositories. Apply the resolved links when creating new drafts.

## Alternatives and consequences

Matching a historical target by slug can bind a link to an unrelated draft. Using publishing redirects as the source index would mix source sites that share a legacy URL and lose mappings for items whose old route needs no redirect. Mutating previously imported drafts would introduce conflicts with ongoing editorial changes. The ledger keeps stable identity and earlier drafts retain their revisions.

## Migration and verification

The database migration adds nullable metadata to the existing claims. Existing claims continue to prevent duplicate imports. The source export does not provide enough information to backfill earlier draft identities safely. Verify atomic storage, source isolation, duplicate matching, cross-batch rewriting, and existing draft revisions on a migrated Docker database.
