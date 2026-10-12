# ADR 0012: Taxonomy assignments and immutable snapshots

## Status

Accepted.

## Decision

Taxonomy owns vocabularies, terms and entry assignments. Entry identifiers are opaque
references; Taxonomy does not query Content persistence. Publishing reads assignments
through a Taxonomy application contract and embeds normalized descriptors into every
new entry snapshot. Public filtering uses a snapshot-owned index.

## Consequences

- mutable taxonomy changes cannot rewrite published responses;
- Content and Taxonomy remain independently owned modules;
- republishing is required to expose assignment or term changes;
- snapshot storage duplicates small descriptors for stable delivery;
- pages, term archives, deletion and localization remain future slices.
