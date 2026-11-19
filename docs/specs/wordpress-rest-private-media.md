# Authenticated WordPress attachment import

## Status

Implemented

## Goals and flow

With `--include-private`, operators can import authorized WordPress image attachments with the same Application Password used for restricted content. Dry-run plans media without retrieving binary files. Apply stores re-encoded variants and binds them to drafts. Existing source and attachment IDs are reused.

## Access and data

Private variants live outside the public document root on a persistent volume. The Admin Studio requires a Morfira editor or owner session to retrieve them through the protected variant endpoint. Responses are private and not stored in caches. Public publishing rejects drafts referencing private image fields, image blocks or Open Graph metadata.

## Security and failure modes

REST edit-context attachment metadata and private binary requests use verified HTTPS on the configured WordPress host. Application Passwords are not forwarded to CDN hosts or redirects. Attachments discovered only through authenticated metadata, linked to restricted or unknown parents, or explicitly private are stored as private. Previously public imported variants cannot automatically become private. Existing download limits, MIME validation, WebP encoding, and transactional rollback apply.

## Acceptance criteria

- [x] Dry-run plans private image references without writing or downloading binaries.
- [x] Apply downloads authorized images and repeated imports reuse IDs without another download.
- [x] Private variants are readable by authorized editors and unavailable to anonymous requests and the public file server.
- [x] Publication does not expose private references.
- [x] Failed applies do not retain newly created private media.

## Rollout

Apply the additive visibility migration and provision the private media volume. Back up the private media archive alongside the public media archive and database.
