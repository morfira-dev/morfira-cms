# Entry relations

## Status

Implemented.

## Goals

- Content schemas can define a single or ordered multiple-entry relation field and its target content type.
- Entry authoring validates that the selected target exists and has the configured type.
- Admin Studio renders relation fields as single or multiple entry selectors and exposes incoming draft references.
- Published snapshots preserve the target entry UUID and exact schema definition.

## Non-goals

- public reverse graph queries, cascading deletion or embedded target payloads;
- recursive relation traversal and automatic target republishing.

## Data and invariants

A `relation` field requires `targetContentTypeId`; other field types reject it. Values
are one UUID, an ordered unique UUID list, or null. Validation crosses modules only through a Schema application port
implemented by Content infrastructure. Snapshots keep stable IDs and never read target
drafts during delivery.

## Acceptance criteria

- [x] Schema and value validation reject malformed or mismatched relations.
- [x] OpenAPI describes relation field metadata.
- [x] Admin Studio supports target type selection and entry selection.
- [x] Multiple relations preserve order and reverse references open their source drafts.
- [x] Backend and frontend gates pass.
