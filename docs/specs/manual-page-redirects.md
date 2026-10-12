# Manual page redirects

## Status

Implemented

## Problem

Automatic slug history preserves renamed page URLs, but migrations and campaign links also require editor-defined permanent redirects. Editable source-to-target string pairs would allow redirect chains, stale targets and cross-page ownership mistakes.

## Goals

- Let authenticated editors add and remove permanent source slugs.
- Resolve every source directly to the owner's current active route.
- Preserve one shared namespace for active routes and redirect sources.

## Non-goals

- External-domain redirects, temporary redirects or wildcard rules.
- Redirects to unpublished pages, entries or arbitrary paths.
- Importing redirects in bulk.

## Actors and permissions

- Authenticated editors can manage redirects for an existing page draft.
- The target page must currently have an active publication route.
- Anonymous users can only follow the resulting public `301` response.

## User and system flows

1. An editor opens a saved page and enters an old slug.
2. The application validates the page, slug, active route and ownership namespace.
3. Persistence atomically reserves the source slug for the stable page identifier.
4. Public HTML and headless resolution join the owner to its current route and return one direct `301` hop.
5. Removing the source slug releases only a redirect owned by that page.

## Contracts

- `POST /api/v1/admin/pages/{id}/redirects` accepts `{ "sourceSlug": string }` and returns the redirect with `201`.
- `DELETE /api/v1/admin/pages/{id}/redirects/{sourceSlug}` is idempotent and returns `204`.
- `GET /api/v1/admin/pages/{id}/redirects` remains the collection read contract.
- Public HTML and headless page lookup retain their existing direct `301` behavior.

## Data and invariants

- A redirect stores a unique source slug and stable page identifier.
- Source and active route slugs share one reserved namespace.
- Source cannot equal the current target slug.
- Repeating creation for the same owner is idempotent.
- Another page cannot remove or claim an owned source slug.
- Unpublishing or deleting the page removes its redirect rows.

## Failure modes

- Missing pages return `404`.
- Invalid identifiers, payloads or slugs return `422`.
- Inactive targets and namespace conflicts return `409`.
- Concurrent unique conflicts are translated to the same domain conflict.

## Security and privacy

- Mutations require the existing authenticated editor session and CSRF token.
- Request objects reject unexpected fields.
- Redirects cannot escape to another origin or inject an arbitrary URL.

## Acceptance criteria

- [x] Admin Studio lists, creates and removes manual redirects.
- [x] Redirect resolution always targets the owner's current active slug.
- [x] Inactive pages and conflicting sources are rejected.
- [x] Cross-page deletion cannot release another owner's redirect.
- [x] OpenAPI and generated frontend types include mutation contracts.

## Verification plan

- Application tests cover success, inactive pages and ownership isolation.
- Frontend API tests cover create and delete requests with CSRF handling.
- Backend and frontend full checks pass.
- Browser inspection confirms the editor controls and inactive-page guidance.

## Rollout and migration

No schema migration is required because manual redirects reuse the existing page-owned redirect table. Rollback removes mutation endpoints and controls while preserving automatic slug history and existing rows.
