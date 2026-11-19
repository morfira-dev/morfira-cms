# ADR 0032: Scope WordPress media download hosts to the import invocation

## Status

Accepted

## Context

WordPress exposes attachment URLs in REST metadata. Some installations serve uploads from a CDN separate from the API. Those URLs are source data and cannot grant permission to contact new hosts.

## Decision

The operator can add exact DNS hostnames for attachments in one CLI invocation. The API host remains implicitly trusted for this purpose. The extra names do not modify the REST metadata destination. Downloads retain public DNS validation and pinning for the actual destination, TLS verification, and the existing restriction on redirects.

## Alternatives and consequences

Automatically following attachment hosts, wildcard domains, and redirect targets would expand the outbound trust boundary based on fetched data. A persistent site-wide list would affect unrelated invocations. The scoped list requires an explicit hostname for each CDN while preserving the same-host default and the local-copy workflow.

## Migration and verification

No schema migration is needed. URL policy and production CLI tests verify opt-in attachment downloads, rejection of unlisted hosts and replay without a transfer.
