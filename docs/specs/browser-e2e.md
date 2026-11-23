# Browser end-to-end verification

## Status
Accepted

## Problem
Unit and HTTP tests do not prove that a browser can complete the critical setup, authoring and publishing journey against the production delivery shape.

## Goals
- Verify setup, login, dashboard, page creation, revision save and public publication in Chromium.
- Verify that a fresh login loads the account's Studio language while the published page retains its content locale.
- Run against the production same-origin `/admin` deployment.
- Keep test credentials ephemeral and isolated to the E2E database.

## Non-goals
- Cross-browser visual regression and load testing.

## Actors and permissions
- CI owns the disposable E2E installation and credentials.

## User and system flows
1. Playwright opens `/admin` on a clean database.
2. It creates the first owner and signs in.
3. It creates, edits and publishes a page.
4. It checks that anonymous HTML and API requests return 404 before the first publication, serve the published revision during further editing, and switch to the new revision after republication.
5. It signs in from a fresh browser context and checks that Studio loads the saved interface language. It opens the published page and checks its content language.

## Contracts
- `pnpm run test:e2e` runs the suite. `pnpm run test:e2e:install` installs Chromium dependencies.

## Data and invariants
- Tests generate unique content and never run against a production database.

## Failure modes
- The suite records trace and screenshots on failure.
- Readiness is awaited before browser actions begin.

## Security and privacy
- Fixtures use synthetic credentials only and do not print passwords.

## Acceptance criteria
- [x] The critical journey passes against a production image.
- [x] Direct Admin Studio navigation is exercised.
- [x] Public HTML and API both expose the published page.

## Verification plan
- Local Docker Playwright run and the release workflow E2E job.

## Rollout and migration
- No product migration. Playwright is a development-only Apache-2.0 dependency.
