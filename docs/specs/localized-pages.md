# Localized pages and URLs

## Status
Implemented

## Problem
Editors cannot publish translations of one page or expose stable locale-aware public URLs.

## Goals
- Version a normalized locale and translation group with every page revision and published snapshot.
- Keep the default locale at `/{slug}` and expose other locales at `/{locale}/{slug}`.
- Return alternate translations and canonical URLs through HTML and headless delivery.
- Prevent route collisions independently inside each locale.

## Non-goals
- Automatic translation, locale negotiation redirects, or localized structured entries.
- Translating Admin Studio itself.

## Actors and permissions
- Editors create and revise localized page variants.
- Anonymous visitors read only active immutable snapshots.

## User and system flows
1. An editor creates a page with a locale and optional existing translation group.
2. Saving creates a revision containing the locale and translation group.
3. Publishing activates the locale-specific route and exposes available alternates.
4. Default-locale HTML remains at the unprefixed route; other locales use a prefix.

## Contracts
- Page create/update/read/revision/public payloads include `locale` and `translationGroupId`.
- Public HTML and headless routes accept default and explicit locale forms.
- Published payloads include `url` and `alternates` derived from active snapshots.

## Data and invariants
- Locale uses a normalized BCP 47 subset such as `en` or `pt-BR`.
- A translation group may contain at most one page per locale.
- Slugs are unique within a locale, not globally.
- Published snapshots retain the locale and translation group they were created with.

## Failure modes
- Invalid locales return `422`; duplicate locale variants or routes return `409`.
- Missing translated routes return `404` without falling back to another language.

## Security and privacy
- Locale and slug values are validated before route construction and never become template paths.

## Acceptance criteria
- [x] Existing pages migrate to the configured default-compatible `en` locale without URL changes.
- [x] Editors can create, revise and publish two variants in one translation group.
- [x] Default and prefixed HTML/API routes resolve only their matching snapshot.
- [x] Canonical and alternate metadata are emitted from immutable active routes.

## Verification plan
- Domain, migration, repository, publishing, HTTP, OpenAPI, frontend and browser tests.

## Rollout and migration
- Existing pages, revisions and snapshots receive locale `en`; each existing page starts its own translation group.
