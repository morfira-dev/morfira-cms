# ADR 0038: Materialize linked section versions in page drafts

## Context
Editors need to refresh a common section across pages. ADR 0029 defines copy-on-insert and preserves historical page and publication content.

## Decision
Keep copy-on-insert as one option. A linked placement records the section ID, version, and member block IDs alongside authored page blocks. Only an explicit editor refresh materializes newer blocks. Save creates a revision and publish promotes it using the existing snapshot pipeline.

## Alternatives and constraints
Render-time lookup would change published output without creating a new page revision. Bulk refresh can use the same materialization model later and needs its own concurrency and editorial controls.

## Consequences
Published delivery has no dependency on the current section catalog. Detached, deleted-source, and historical placements retain concrete blocks.

## Migration impact
Add nullable draft and revision metadata columns. Old rows require no backfill. Public contracts are unchanged.

## Verification
Check placement invariants, persistence, restoration, and independent published rendering through HTML and API.
