# CDN images in Admin Studio WordPress imports

## Status

Implemented

## Problem and goals

Owners importing public WordPress content from Studio can explicitly allow the CDN that serves its images. Existing attachment planning, image binding, and replay behavior apply to these images.

## Actors and flow

An owner enables public image downloads and optionally enters up to eight exact CDN hostnames, one per line. Changes to the list invalidate the current preview and material selection. The preview checks attachment URL policy against the supplied list without fetching files. Apply uses the allowlist for that request. The WordPress REST hostname remains implicitly allowed.

## Contracts

The REST import JSON accepts optional `allowedMediaHosts`, a list of up to eight exact public DNS hostnames. The property is accepted only with `downloadMedia: true`. Names are case-insensitive. The API rejects malformed values before reading WordPress. A missing or empty list retains the source-host default. The report and history formats stay compatible.

## Security and data

Treat hostnames as request input, separate from downloaded metadata. The allowlist applies to anonymous HTTPS attachment downloads on port 443. REST collections continue to use the configured API hostname. Exact matching, public DNS validation and pinning, TLS verification, the redirect prohibition, existing attachment budgets and transactional compensation all apply. Authentication to WordPress remains a CLI workflow. The form keeps the list for the current wizard session and the server keeps no list in reports, history, or settings.

## Acceptance criteria

- [x] Studio sends an explicit valid list for preview and apply and resets a preview after host edits. The default request continues to allow only source-host images.
- [x] Invalid shape, disabled image downloads, malformed hosts and oversized lists return 422 before REST reads.
- [x] Allowed CDN attachments are planned without file transfers and can be imported and bound on apply. Unlisted hosts are skipped and replays reuse recorded assets.
- [x] Existing transfer guards, failure rollback, authorized access, and CLI behavior remain covered.

## Verification and rollout

Run backend and frontend gates and the Chromium importer journey. Verify against controlled metadata and image sources. No database migration is required.
