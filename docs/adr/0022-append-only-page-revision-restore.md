# ADR 0022: Restore pages by appending a revision

- Status: Accepted
- Date: 2026-10-12

## Context

Page revisions are immutable, but recovery needs a clear relationship between history, the mutable draft head and published snapshots.

## Decision

Restoring a page copies the selected historical payload into a new draft revision using the current head as the optimistic-lock base. It never rewrites history and never changes an active publication.

## Alternatives and constraints

- Moving the head pointer backwards was rejected because revision numbers would stop representing a monotonic edit history.
- Restoring and publishing in one action was rejected because editorial recovery and public delivery are separate permissions and intentions.

## Consequences

- Every restoration is auditable as another revision.
- Slug uniqueness and current-version conflicts are evaluated exactly like an ordinary edit.
- Public HTML and headless delivery remain unchanged until explicitly published.

## Migration impact

No schema migration is required.

## Verification

Application, persistence and HTTP tests prove append-only behavior, stale-write rejection and unchanged delivery semantics.
