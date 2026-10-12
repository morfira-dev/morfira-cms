# ADR 0013: Entry relations by stable identifier

## Status

Accepted.

## Decision

Relation fields store one target entry UUID and declare one target content-type UUID.
Schema validation uses an application lookup port implemented by Content. Published
snapshots preserve the identifier rather than embedding another snapshot.

## Consequences

Relations stay lightweight and avoid recursive payloads. Clients resolve targets through
their public content routes. Multi-value relations, reverse queries and deletion policy
require later explicit contracts.
