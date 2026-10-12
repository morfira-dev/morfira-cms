# Admin Studio routing

## Status

Implemented

## Problem

Admin Studio currently renders every workspace on one long page while sidebar items are inert. Editors cannot bookmark a workspace, use browser navigation or focus on one task at a time.

## Goals

- Give every primary workspace a stable `/admin/*` URL.
- Make sidebar navigation, browser back/forward and direct entry agree.
- Render only the selected workspace while retaining shared feature state where needed.

## Non-goals

- Server-side rendering of Admin Studio.
- A general-purpose routing framework or nested modal routes.
- Persisting unsaved form state across workspace changes.

## Actors and permissions

- Authenticated editors can open editorial routes.
- The Users route is visible and usable only by owners.
- Backend authorization remains authoritative for every API request.

## User and system flows

1. An editor selects a sidebar item or opens an Admin Studio URL directly.
2. The client normalizes the pathname to a known route.
3. History API navigation changes the selected workspace without a reload.
4. Back and forward restore the matching workspace.
5. Unknown admin paths replace to the dashboard route.

## Contracts

- Routes include dashboard, pages, media, navigation, content types, taxonomies, entries, users and settings.
- Navigation uses real links so standard browser behavior remains available.
- `/` redirects client-side to `/admin/pages` for backward compatibility with the current development entry point.

## Data and invariants

- Route definitions have one stable identifier, label and pathname.
- At most one workspace is active.
- Restricted navigation items are derived from the authenticated user's roles.

## Failure modes

- Unknown paths resolve to `/admin` without throwing.
- A non-owner opening `/admin/users` sees an access-denied workspace and no user data request is sent.

## Security and privacy

- Client route visibility is convenience, not authorization.
- Session and role checks continue to be enforced by Symfony.

## Acceptance criteria

- [x] Sidebar links select one workspace and update the URL.
- [x] Direct entry and browser back/forward select the correct workspace.
- [x] Unknown paths recover to the dashboard.
- [x] Users navigation is hidden from editors.
- [x] Routing adds no production dependency.

## Verification plan

- Pure route resolution tests cover known, root and unknown paths.
- Frontend tests, type checking and production build pass.
- Browser evidence verifies link selection and back navigation.

## Rollout and migration

No data migration is required. Existing `/` entry remains compatible and is normalized after authentication.
