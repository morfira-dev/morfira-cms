# WordPress REST media downloads

## Status

Implemented

## Problem and goals

Import supported WordPress images through the REST command without preparing a local uploads tree. Preserve inline and featured image bindings, dry-run, and source-scoped replay protection.

## Actors and flow

An operator provides the public HTTPS WordPress REST namespace and a stable source key. The reader collects metadata as before. Without `--media-dir`, apply downloads previously unclaimed eligible attachments from that site's HTTPS host and any [explicitly allowed CDN hosts](wordpress-rest-cdn-media.md). An explicit `--media-dir` selects the local-copy workflow. WXR retains its local media behavior.

## Contracts

Dry-run validates metadata and download URL policy and reports planned attachments. It fetches the REST collections without requesting image bytes or writing application state. Planning cannot promise availability, byte validity, or successful decoding. Apply feeds bounded temporary originals to the existing immutable Media processing pipeline. The JSON item and media outcomes retain their existing shape.

## Data and invariants

The shared source key and attachment ID ledger is checked before a download. Claims, media records, image bindings and drafts use the existing transaction. Reusable mappings continue to supply existing asset IDs. Temporary originals are scoped to individual requests, and generated variants are compensated on rollback.

## Failure modes and security

HTTPS attachment links on the default port are supported for the configured source and explicitly allowed hosts. Public DNS addresses are validated and pinned. Credentials, arbitrary hosts, unsafe paths, redirects and proxies are excluded. Each download has a 20-second transfer timeout and the existing 10 MiB limit. Invalid URLs, oversize files and invalid image bytes produce item skips. Fetch and temporary-storage failures abort apply and allow a clean retry. No original bytes become public.

## Acceptance criteria

- [x] Dry-run plans eligible remote attachments and their bindings without downloading bytes or writing CMS state.
- [x] Apply imports attachment bytes as normal WebP assets and binds inline and featured images.
- [x] Replay does not download or duplicate already imported attachments.
- [x] Disallowed URLs are rejected and a download failure leaves the batch eligible for retry.

## Verification

Transport policy tests, full backend checks, and fixture-driven production CLI apply and replay on MariaDB.
