# WordPress WXR draft import

## Status

Implemented

## Problem

Operators need a repeatable way to bring WordPress content into Morfira and review the proposed URL mapping before publishing.

## Goals

- Read a local WordPress WXR export and report the proposed imports in JSON.
- Import pages as page drafts and posts as structured entry drafts with a rich-text body.
- Preserve existing content across reruns, including modified exports from the same source.

## Non-goals

Remote fetches, attachment and taxonomy migration, author accounts, nested page URLs, automatic redirects, block conversion, and automatic publishing are follow-up work. The JSON report is a mapping for manual review. Embedded media and unsupported markup may be dropped by the shared rich-text sanitizer.

## Actors and permissions

An operator with shell and local file access runs the command. The CLI exposes no HTTP entry point.

## User and system flows

1. Run `php bin/cms cms:import:wordpress FILE --source=legacy-site` to inspect the plan.
2. Review proposed paths and skips, then add `--apply` to create drafts.
3. Review, repair and publish the drafts explicitly in Admin Studio.

## Contracts

The source key is a stable lowercase installation identifier of up to 80 characters using letters, digits and hyphens. Reports contain counts, unsupported post types and item outcomes, including source ID, status, old URL path and proposed destination path when available. A proposed path is not a reservation in the public router. Use the same source key for exports of the same installation.

## Data and invariants

Content owns the import ledger. A unique source key and WordPress ID identify a previously imported item even when the export changes or its draft is later deleted. Pages use the installation's default locale and posts use the `wordpress-posts` schema with an optional `body` rich-text field. Existing compatible schemas can be reused. All writes and claims run in one transaction through normal authoring use cases.

## Failure modes

Unsupported types and statuses, password-protected items, invalid values, incompatible schemas and detected draft slug collisions are reported and skipped. Invalid XML fails the whole command. Unexpected write failures and concurrent conflicts roll back the complete apply. Re-run the dry-run after resolving reported issues or a concurrent change.

## Security and privacy

Read bounded local XML with DTDs and entities disabled. Source URLs are never fetched. Public and eligible draft statuses import as drafts. Private, password-protected and trashed content is skipped. Reports exclude titles, body text, passwords and URL query strings.

## Acceptance criteria

- [x] Dry-run creates no schema, drafts, routes or ledger entries.
- [x] Apply creates ordinary sanitized drafts and leaves SSR and the public API unchanged.
- [x] Replay with the same source key and ID never replaces a draft or recreates it after deletion.
- [x] Malformed or unsafe XML is rejected, and draft slug conflicts are reported.
- [x] Failed applies roll back both drafts and ledger entries.

## Verification plan

Parser boundary tests and the full backend gate cover the reader. Run `CMS_TEST_WORDPRESS_IMPORT=1 ./scripts/test-e2e.sh` for the Docker integration exercise. It verifies dry-run, draft persistence, sanitation, draft slug collisions, edited-export replay, transaction rollback and anonymous delivery. Ledger rows have no draft foreign key and survive draft lifecycle changes.

## Rollout and migration

Apply the additive ledger migration before running the command. Back up database and media before an apply on a live installation. Preserve the ledger on upgrade to retain replay protection.
