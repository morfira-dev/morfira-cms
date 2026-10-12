# Entry taxonomy

## Status

Implemented.

## Problem

Structured entries need reusable categories and tags for editorial organization
and public discovery without embedding classification into every content schema.

## Goals

- Editors can create flat or hierarchical taxonomies and terms.
- Editors can assign existing terms to an entry draft.
- Publication copies assigned terms into the immutable entry snapshot.
- Public entry collections can be filtered by taxonomy and term slugs.

## Non-goals

- term editing, deletion, ordering or translation;
- taxonomy assignment to pages;
- public term archive HTML pages;
- automatic inheritance from parent terms.

## Actors and permissions

Authenticated editors manage taxonomies, terms and entry assignments. Public API
clients can read snapshot taxonomy data and filter published collections.

## User and system flows

1. An editor creates a taxonomy with a unique slug and hierarchy mode.
2. The editor creates terms; a parent is accepted only for hierarchical taxonomies.
3. The editor replaces an entry's complete term assignment.
4. Publishing copies the current assigned term descriptors into the snapshot.
5. Public clients optionally filter by `taxonomy` and `term` query parameters.

## Contracts

Admin endpoints live under `/api/v1/admin/taxonomies`; entry assignments live at
`/api/v1/admin/entries/{id}/terms`. Public collection filtering requires both
`taxonomy` and `term`, and matches immutable snapshot data only.

## Data and invariants

- taxonomy slugs are globally unique;
- term slugs are unique within one taxonomy;
- a parent term belongs to the same hierarchical taxonomy;
- an entry assignment is a set and is replaced atomically;
- snapshot taxonomy descriptors never change after publication.

## Failure modes

Malformed input returns `422`, missing resources return `404`, and uniqueness or
stale assignment conflicts return `409`. Supplying only one public filter returns
`422`.

## Security and privacy

All authoring mutations retain session, editor-role and CSRF requirements. Public
responses contain only taxonomy descriptors already copied into a published snapshot.

## Acceptance criteria

- [x] Taxonomies and terms can be created and listed.
- [x] Entry term assignments can be read and replaced.
- [x] Published entry snapshots contain immutable term descriptors.
- [x] Public collections filter by taxonomy and term slugs.
- [x] OpenAPI types, backend tests and frontend checks pass.

## Verification plan

Backend application tests cover hierarchy, assignment, snapshot immutability and
filtering. OpenAPI generation, PHPStan, frontend tests and production build run in
Docker. The Admin Studio is verified in a browser.

## Rollout and migration

The additive migration creates taxonomy-owned tables and snapshot term indexes.
Existing published snapshots receive an empty taxonomy object and remain readable.
