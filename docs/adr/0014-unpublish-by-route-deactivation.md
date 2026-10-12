# ADR 0014: Unpublish by route deactivation

- Status: Accepted
- Date: 2026-10-12

## Context

Public delivery is backed by immutable snapshots and a mutable active-route table. Removing snapshots during unpublish would destroy audit history and make publication behavior harder to reproduce.

## Decision

Unpublish deletes only the active route. Page redirects owned by that route are deleted, and a pending schedule is cancelled. Drafts, revisions and published snapshots remain unchanged.

The operation is idempotent for an existing draft. Physical draft deletion is a separate lifecycle operation because entry relations require an explicit reverse-reference policy.

## Consequences

- Public delivery stops immediately through the same route lookup used for reads.
- Historical snapshots remain immutable.
- Editors can republish an existing revision deterministically.
- Storage reclamation needs a future retention and purge policy.
