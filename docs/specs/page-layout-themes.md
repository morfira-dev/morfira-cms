# Page layouts and built-in themes

## Status
Accepted

## Problem
Published pages have one hard-coded presentation and cannot bind navigation to named regions.

## Goals
- Version page layout with the draft and immutable publication snapshot.
- Provide `standard` and `wide` layouts under the built-in `morfira` theme.
- Bind optional published navigation codes to header and footer regions.
- Expose identical layout metadata through headless delivery.

## Non-goals
- Uploaded executable themes, arbitrary Twig paths and marketplace extensions.

## Actors and permissions
- Editors select only registered theme/layout identifiers and navigation codes.

## User and system flows
1. The editor chooses a layout and optional navigation placements.
2. Saving appends a page revision containing the layout.
3. Publishing copies the layout into an immutable snapshot.
4. Complete CMS resolves placed navigation through Navigation application contracts; headless clients receive the same placement codes.

## Contracts
- `Page`, `PageRevision`, update requests and `PublishedPage` include `layout`.

## Data and invariants
- Theme is `morfira`; layout is `standard` or `wide`; placement codes are nullable valid navigation codes.

## Failure modes
- Unknown identifiers return `422`; absent published menus render an empty region without leaking drafts.

## Security and privacy
- No user-controlled template path or executable theme code is accepted.

## Acceptance criteria
- [x] Layout settings are revisioned and restored with pages.
- [x] Published snapshots retain their original layout.
- [x] Header and footer render only published navigation.
- [x] Headless delivery includes immutable layout metadata.

## Verification plan
- Domain, publishing, template, HTTP, generated-type and frontend tests.

## Rollout and migration
- Existing pages and snapshots receive the built-in standard layout with empty placements.
