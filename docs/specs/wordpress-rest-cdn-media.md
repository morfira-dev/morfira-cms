# WordPress REST CDN media

## Status

Implemented

## Problem and goals

Import images served on a trusted CDN without copying the WordPress uploads tree. Preserve the existing source-host default, dry-run, idempotency and transactional behavior.

## Actors and flow

An operator can repeat `--media-host=HOST` with `--api-url` to allow specific HTTPS attachment hosts for one run. The importer still reads REST collections from the configured WordPress API host. Without `--apply`, allowed attachment URLs are planned without downloading bytes. Apply imports allowed attachments and preserves the normal image bindings.

## Contracts and invariants

The list contains exact DNS names and is case-insensitive. The REST API host is always allowed. There is no wildcard or parent-domain expansion. The option is valid only for REST downloads and cannot be combined with the local file or `--media-dir`. Malformed host arguments fail before the REST read. The report format and existing source key and attachment ID ledger are unchanged.

## Failure modes and security

Unlisted or otherwise invalid attachment URLs produce item skips. The downloader checks each actual destination host's public DNS results and pins those addresses, verifies TLS, uses only HTTPS on the default port, and follows no redirects. Existing byte and time limits and the Media re-encoding pipeline still apply. Temporary failures roll back apply. A previous attachment claim can be reused without a new request.

## Acceptance criteria

- [x] The CLI accepts repeated exact CDN hostnames and rejects malformed arguments before reading the source.
- [x] Dry-run reports allowed CDN attachments without downloading bytes or writing state.
- [x] Apply imports allowed CDN assets and skips unlisted hosts.
- [x] Replay reuses the original asset and does not download it again.

## Verification

Transport policy and CLI tests, plus fixture-driven production integration on MariaDB.
