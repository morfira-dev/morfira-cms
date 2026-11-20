# Public WordPress REST images in Admin Studio

## Status

Implemented

## Problem and goals

Owners migrating a small public WordPress site can bring its images into Morfira while creating drafts in Studio. Inline images and featured images use the existing attachment bindings and media ledger.

## Actors and flow

An owner can enable downloading public images on the REST source form. The report points to this option and counts the attachments that still require files when downloads are off. Changing the option invalidates the current preview. The preview checks attachment metadata and plans eligible images without fetching files or writing data. Applying plans or reuses the source's public attachments and imports the chosen content. The option covers attachments from the source snapshot, including when the owner selects only some pages and posts. Studio explains this scope before applying.

## Contracts

The existing REST import JSON request accepts optional boolean `downloadMedia`, defaulting to false. The report uses the existing image and media fields. Invalid options are rejected. An exhausted request budget or remote transfer failure returns a generic 502 and rolls back the apply.

## Security, limits and data

Use anonymous public HTTPS from the REST hostname or an [explicitly allowed CDN](wordpress-studio-rest-cdn-media.md), pinned public DNS, verified TLS and no redirects. Keep the existing bounded metadata reader. Permit at most eight new attachment transfers with a shared 20-second attachment transfer budget and the existing upload limit per image. Validate the remaining budget after DNS and use it to cap cURL time. Synchronous DNS may return after the deadline. Reuse the decoder, re-encoder and ledger. A replay skips already mapped images before spending the transfer budget. Unsafe URLs and invalid image files remain per-item invalid results. Network failure, budget exhaustion or failed history persistence roll back the transaction and compensate newly stored files.

## Acceptance criteria

- [x] The option defaults off and toggling it resets preview and selection. The selected request mode carries the option only when enabled.
- [x] Dry-run makes no file downloads. Apply imports and binds valid public images. Replay neither downloads nor duplicates mapped images.
- [x] Same-host restrictions, request limits, and rollback cover HTTP-triggered image transfer, including history-write failure.
- [x] Existing WXR, REST metadata-only and CLI behavior continue to work.

## Verification and rollout

Run backend and frontend gates, the WordPress CLI fixture journey and a Chromium Studio scenario. Use controlled remote snapshot and image fixtures. No database migration is required.
