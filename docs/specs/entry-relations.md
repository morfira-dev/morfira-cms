# Entry relations

## Status

Implemented.

## Goals

- Content schemas can define a single-entry relation field and its target content type.
- Entry authoring validates that the selected target exists and has the configured type.
- Admin Studio renders relation fields as entry selectors.
- Published snapshots preserve the target entry UUID and exact schema definition.

## Non-goals

- multiple relations, reverse indexes, cascading deletion or embedded target payloads;
- relation graph queries and automatic target republishing.

## Data and invariants

A `relation` field requires `targetContentTypeId`; other field types reject it. Values
are UUIDs or null. Validation crosses modules only through a Schema application port
implemented by Content infrastructure. Snapshots keep stable IDs and never read target
drafts during delivery.

## Acceptance criteria

- [x] Schema and value validation reject malformed or mismatched relations.
- [x] OpenAPI describes relation field metadata.
- [x] Admin Studio supports target type selection and entry selection.
- [x] Backend and frontend gates pass.
