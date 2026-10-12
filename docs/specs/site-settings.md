# Site settings

## Status
Implemented

## Problem
Admin Studio exposes a Settings route but cannot persist installation identity or regional defaults.

## Goals
- Let owners read and update the site name, default locale and timezone.
- Keep updates optimistic and expose a stable OpenAPI contract.

## Non-goals
- Theme configuration, secrets, environment URLs and per-locale content.
- Publishing site settings to public delivery in this slice.

## Actors and permissions
- Any authenticated editor can read settings.
- Only an owner can update settings; other actors receive `403`.

## User and system flows
1. Admin Studio loads the current singleton settings document.
2. An owner edits validated values and submits the current version.
3. The backend atomically applies the change and increments the version.

## Contracts
- `GET /api/v1/admin/settings` returns `SiteSettings`.
- `PUT /api/v1/admin/settings` accepts `UpdateSiteSettingsRequest` and is CSRF protected.

## Data and invariants
- Exactly one row with identifier `1` exists after migration.
- Names are 1-120 characters; locale is a normalized language tag; timezone is an IANA identifier.
- Updates use integer optimistic locking.

## Failure modes
- Invalid values return `422`; stale versions return `409`; non-owners receive `403`.

## Security and privacy
- Settings contain no secrets or credentials.
- Authorization is enforced server-side.

## Acceptance criteria
- [x] The default settings are readable after migration.
- [x] An owner can update all three values.
- [x] A stale version cannot overwrite a newer update.
- [x] Admin Studio presents loading, success, validation and conflict states.

## Verification plan
- Application and HTTP tests, migration execution, generated client types, frontend API tests and build.

## Rollout and migration
- An additive migration creates and seeds the singleton. Rollback removes only this new table.
