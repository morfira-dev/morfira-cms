# ADR 0025: Guard media deletion through module usage contracts

- Status: Accepted
- Date: 2026-10-12

## Context
Media cannot inspect another module's persistence without violating ownership, while deletion must protect mutable revisions and immutable snapshots.

## Decision
Content and Publishing expose explicit application-level media usage contracts backed by their own persistence adapters. Media deletion queries both contracts and refuses deletion when either reports a reference.

## Alternatives and constraints
- Cross-module SQL inside Media was rejected.
- Deleting published files while retaining snapshots was rejected because it would break immutable delivery.

## Consequences
Usage rules remain with the owning module. New modules that reference media must implement and register another usage contract before deletion can remain safe.

## Migration impact
No reference index is introduced; existing JSON documents are inspected by their owners.

## Verification
Application tests cover draft, revision, published and unused assets.
