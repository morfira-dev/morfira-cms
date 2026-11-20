# Public WordPress REST import in Admin Studio

## Status

Implemented

## Problem and goals

Owners can preview and apply a public WordPress REST migration in their browser. The wizard reuses its source keys, selectable rows, saved reports and draft links for both input formats.

## Actors and flow

1. An owner selects the REST source and enters its public HTTPS WordPress REST namespace and a stable source key.
2. Studio reads the remote snapshot and shows a dry-run report. Changing the source mode, URL or key discards the current plan.
3. Applying reads a fresh snapshot and creates drafts using the existing ledger. Selected IDs must belong to the fresh snapshot.
4. A successful apply and its history report commit together. Replays report existing drafts as already imported.

## Contracts

`POST /api/v1/admin/imports/wordpress/rest` accepts JSON with `apiUrl`, `source`, `mode`, optional apply-only `selectedIds` and optional boolean `downloadMedia`. The request is limited to 128 KiB. Reports use `input: rest` and the existing report shape. WXR upload and the CLI retain their request formats. The history endpoints accept reports for both inputs.

## Data and invariants

Studio imports public content, terms, author profiles and eligible redirects. Attachment metadata supports reuse of files already mapped to the same WordPress source. Owners can enable [bounded image imports](wordpress-studio-rest-media.md) and enter [exact CDN hosts](wordpress-studio-rest-cdn-media.md). The CLI handles larger imports and authenticated WordPress access.

## Security and failure handling

Both modes require an owner session and CSRF token. Only the canonical public HTTPS namespace and supported collection paths are requested. Studio uses public DNS validation and pinning, TLS verification and no redirects. The outbound budget is 12 collection requests, 4 MiB of metadata and a 12-second deadline enforced around synchronous DNS resolution and on each HTTPS transfer. PHP's DNS call can finish after the deadline, in which case the transfer is skipped. User-supplied credentials and extra fields are rejected. The metadata snapshot is complete before the write transaction starts. Failed or incomplete remote reads return a generic error with no remote payload, and create no drafts or history.

## Acceptance criteria

- [x] The REST wizard previews and applies selected public items, and can reopen the saved report with draft links.
- [x] Dry-run and failed remote reads create no drafts or history. Replay preserves existing content.
- [x] Both modes require owner access and CSRF. Invalid URL or request input is rejected, and remote reads are bounded.
- [x] Existing WXR and CLI imports retain their behavior.

## Verification and rollout

Verify URL validation, transport budgets, HTTP authorization and report persistence with backend tests. Run Chromium against Studio and run the WXR and REST CLI journey on Docker MariaDB. The change reuses the current history and ledger tables. See [ADR 0037](../adr/0037-studio-wordpress-rest-boundary.md).
