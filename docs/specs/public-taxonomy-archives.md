# Public taxonomy archives

## Status

Implemented

## Problem

Visitors and search engines need an index of published entries belonging to one taxonomy term. Building that page from mutable authoring tables could expose drafts or silently change historical publication output.

## Goals

- Render a cacheable public archive from immutable entry snapshots.
- Keep server-rendered and headless taxonomy filtering semantically aligned.
- Provide stable pagination, canonical URLs and conditional responses.

## Non-goals

- Custom archive layouts or theme bindings.
- Cursor pagination, archive feeds or taxonomy sitemaps.
- Redirecting renamed taxonomy URLs.

## Actors and permissions

- Anonymous visitors and crawlers can read archive pages.
- Editors publish the entries that become eligible; drafts remain private.

## User and system flows

1. A visitor requests a content type, taxonomy slug and term slug.
2. Delivery queries the immutable published-entry index with those filters.
3. The archive renders up to 20 entries and the term label copied into the snapshot.
4. The response includes public cache directives, a canonical URL and an ETag.
5. Conditional requests return `304` while the selected snapshot set is unchanged.

## Contracts

- `GET /content/{type}/taxonomy/{taxonomy}/{term}` renders the archive.
- The optional `page` query parameter is a positive integer.
- Matching headless clients continue to use `taxonomy` and `term` on `/api/v1/content/entries/{type}`.
- No existing public route or `/api/v1` payload changes.

## Data and invariants

- Only active published entry snapshots participate.
- Filtering matches both taxonomy and term slugs in one snapshot descriptor.
- The visible term name comes from a matching immutable descriptor.
- Page size is 20; empty and out-of-range result pages are not valid archives.
- ETags include snapshot identifiers, total count and page number.

## Failure modes

- Unknown content types, empty archives and out-of-range pages return `404`.
- Invalid page values return `422`.
- Mutable taxonomy changes do not alter already published archive descriptors.

## Security and privacy

- Delivery never queries drafts or mutable assignments.
- Twig autoescaping remains enabled for snapshot titles and descriptor labels.
- Public cache headers apply only to immutable delivery data.

## Acceptance criteria

- [x] A matching archive renders published entries and the snapshot term label.
- [x] Draft, unpublished and non-matching entries remain absent.
- [x] Invalid, empty and out-of-range archive requests fail predictably.
- [x] Responses provide canonical, pagination, cache and ETag behavior.
- [x] Existing headless taxonomy filters remain unchanged.

## Verification plan

- Presentation tests cover matching, missing, invalid and out-of-range archives.
- Backend tests and PHPStan pass.
- Symfony route inspection confirms the public archive route.

## Rollout and migration

No migration or backfill is required. Existing published snapshot taxonomy indexes are reused. Removing the route rolls back the web surface without affecting headless filtering or stored snapshots.
