# ADR 0029: Reusable sections use copy-on-insert

## Context
Live shared sections would make old revisions and published pages change without an explicit page revision.

## Decision
Reusable sections are versioned authoring aggregates. Insertion copies their blocks with fresh block identifiers into the page draft.

## Alternatives and constraints
Live references and render-time section lookup are rejected because they violate immutable publication and make deletion ambiguous.

## Consequences
Editors get reusable composition without hidden coupling. Updating a section does not propagate automatically.

## Migration impact
An additive table stores sections; page schemas remain compatible.

## Verification
Section lifecycle and independent-copy tests.
