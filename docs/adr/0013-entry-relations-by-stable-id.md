# ADR 0013: Entry relations by stable identifier

## Status

Accepted.

## Decision

Relation fields declare one target content-type UUID and store either one target entry
UUID or an explicitly ordered list of target UUIDs. Schema validation uses an application
lookup port implemented by Content. Published snapshots preserve identifiers rather than
embedding another snapshot.

## Consequences

Relations stay lightweight and avoid recursive payloads. Clients resolve targets through
their public content routes. Multi-value fields are bounded to 100 unique targets and use
the normalized relation index described by ADR 0015. Reverse queries still require a later
explicit contract.
