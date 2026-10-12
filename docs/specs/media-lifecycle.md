# Media lifecycle

## Status
Accepted

## Problem
Uploaded media cannot be organized, searched, updated or safely deleted.

## Goals
- Add collection and tag metadata with editable alt text.
- Search and filter assets in Admin Studio.
- Delete unused assets while rejecting every draft, revision or published-snapshot reference.
- Remove generated files after metadata deletion.

## Non-goals
- Video, external object storage, image editing and automatic asset deduplication.

## Actors and permissions
- Editors and owners manage media; anonymous users only read immutable media URLs referenced by published content.

## User and system flows
1. An editor uploads or updates metadata for an image.
2. Admin Studio filters the loaded library by query and collection.
3. Delete checks Content and Publishing application usage contracts.
4. Unused metadata and generated variants are removed; referenced assets return `409`.

## Contracts
- `PUT /api/v1/admin/media/{id}` updates alt, collection and tags.
- `DELETE /api/v1/admin/media/{id}` returns `204` or `409` with usage reasons.

## Data and invariants
- Collection is optional and limited to 120 characters; tags are unique normalized strings.
- References in current drafts, retained revisions and publication snapshots block deletion.

## Failure modes
- Invalid metadata returns `422`; missing assets return `404`; referenced assets return `409`.

## Security and privacy
- Paths are always derived from validated UUIDs; clients never provide filesystem paths.

## Acceptance criteria
- [x] Metadata survives persistence and appears in OpenAPI-generated clients.
- [x] Search and collection filters work without another server round trip.
- [x] Referenced assets cannot be deleted.
- [x] Unused assets and their variants can be deleted.

## Verification plan
- Domain/application/HTTP tests, migration execution, frontend API tests and build.

## Rollout and migration
- An additive migration gives existing assets an empty tag list and no collection.
