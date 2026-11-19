# Authenticated WordPress REST import

## Status

Implemented

## Problem and goals

Operators can migrate WordPress private, draft, pending, scheduled and password-protected posts and pages that their WordPress account can edit. The existing dry-run and replay ledger apply to authenticated imports.

## Actors and flow

The operator supplies `--api-url` and `--include-private` and provides `CMS_WORDPRESS_USERNAME` and `CMS_WORDPRESS_APPLICATION_PASSWORD` in the command environment. The reader fetches posts and pages in edit context before planning or applying. WordPress remains responsible for access to each requested item. Apply creates ordinary Morfira drafts.

## Contracts and invariants

WordPress Application Passwords are sent as HTTPS Basic authentication only to the configured REST posts and pages collections. Authenticated results retain the WordPress status in the CLI report and use authorized raw content for restricted items. Passwords and restricted source links are excluded from the report. Only public source items qualify for old URL redirects. The existing source key and WordPress item ID prevent duplicates across modes.

Media, author and taxonomy metadata still use anonymous requests. Files attached to restricted items are not migrated automatically because the Morfira media surface serves variants publicly. Existing public attachments can still be referenced where eligible.

## Failure modes and security

Missing credentials or using the option with WXR fails before the source read. WordPress HTTP errors and incomplete batches fail without applying. Credentials remain invocation-scoped and are never forwarded to uploads, CDN hosts, redirect targets, or persisted in Morfira. Restricted drafts have no public routes. Publishing a restricted import later is an explicit editorial action that makes it public in Morfira. WordPress access settings and schedules are not replicated.

## Acceptance criteria

- [x] Public mode never uses credentials and keeps its existing filtering behavior.
- [x] Opt-in dry-run reads authorized restricted items without saving them.
- [x] Apply creates restricted drafts without public routes or legacy redirects and does not download restricted attachments.
- [x] Reapplying the same source leaves prior drafts and claims intact with no duplicates.
- [x] Authentication or partial reads fail before persistence.

## Verification

Transport and mapper tests, reader scope tests, and a production CLI fixture against disposable MariaDB.
