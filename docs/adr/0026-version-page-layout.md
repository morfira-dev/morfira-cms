# ADR 0026: Version layout and navigation placements with pages

- Status: Accepted
- Date: 2026-10-12

## Context
Mutable global theme settings would let public output change without a publication event and would violate immutable delivery semantics.

## Decision
Store a validated page layout value in drafts, revisions and published snapshots. It names a registered built-in theme, layout and optional header/footer navigation codes. Complete CMS resolves only published navigation; headless delivery returns the same metadata.

## Alternatives and constraints
- Arbitrary Twig paths and uploaded PHP themes are prohibited.
- Mutable global placement settings were rejected.

## Consequences
Layout changes follow preview, revision, restore and publish workflows. Future theme packages must register stable identifiers and templates through an extension contract.

## Migration impact
Existing records receive `morfira/standard` with empty placements.

## Verification
Migration, domain, revision, publication and template tests.
