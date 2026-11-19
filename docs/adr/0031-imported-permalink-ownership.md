# ADR 0031: Imported permalinks follow stable published targets

## Status

Accepted

## Context

Migrated WordPress URLs include nested paths and query-based permalinks. Destinations are first created as drafts. The existing page slug history supports active pages and has a separate lifecycle.

## Decision

Publishing owns a separate exact-source catalog of imported aliases. Entries identify a page or post by stable ID. Resolution reads the active public route at request time. A native public route or native redirect takes precedence, with an exception for exact legacy query paths that share the home URL. The response uses a same-origin location and disables storage so a cached permanent redirect cannot outlive a publication.

## Alternatives and constraints

Raw source-to-target URLs, wildcard matches and redirect chains are outside this contract. Aliases are dormant until publication and remain recorded after unpublishing. A later publication may reactivate the same alias.

## Migration impact and verification

The catalog is additive and independent of manual page redirects. Tests cover allowed source forms, route precedence, stable targets and production publication lifecycle.
