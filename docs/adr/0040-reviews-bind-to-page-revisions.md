# ADR 0040: Bind editorial reviews to immutable page revisions

## Context
A review decision attached to a mutable page would become ambiguous after another editor saves changes.

## Decision
Store editorial review records in Content against an exact page revision. Persist the author, decision maker, and reviewed revision metadata. Permit one atomic terminal decision per review. Owners decide reviews using the current role model.

## Alternatives and constraints
A status stored on the mutable page would silently carry approval to subsequent edits. A separate revision-bound record keeps the meaning of a decision stable.

## Consequences
Editors can continue drafting while another revision is reviewed. The publication pipeline continues to serve immutable snapshots and is independent of the optional review queue in this slice.

## Migration impact
Add the review table. Existing revisions and snapshots require no backfill.

## Verification
Check revision identity, decision concurrency, role enforcement, and unchanged public publication.
