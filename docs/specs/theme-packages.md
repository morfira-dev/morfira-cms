# Theme packages

## Status
Implemented

## Problem
Pages are restricted to one hard-coded theme and templates are validated inside the page value object.

## Goals
- Discover trusted theme manifests shipped with the installation.
- Expose registered themes, templates and regions to Admin Studio.
- Validate page layout against a registry and render only registered Twig templates.

## Non-goals
- Uploading archives, remote installation or executing theme PHP from Admin Studio.

## Actors and permissions
- Editors select registered themes and templates; operators install packages through deployment.

## User and system flows
1. The application boots trusted theme manifests.
2. Admin Studio reads the catalog and selects a theme/template.
3. Saving validates stable identifiers; publishing freezes them in the snapshot.
4. Public rendering resolves the registered Twig template, never a user-provided path.

## Contracts
- `GET /api/v1/admin/themes` returns theme metadata, templates and supported regions.
- Existing `layout.theme` and `layout.template` fields remain backward compatible.

## Data and invariants
- Theme and template identifiers are lowercase slugs.
- The built-in `morfira` package remains the default and cannot be absent.

## Failure modes
- Unknown or disabled identifiers return `422`; missing render resources fail closed.

## Security and privacy
- Only deployment-trusted manifests and container-registered services participate.

## Acceptance criteria
- [x] The catalog exposes at least two themes with registered templates.
- [x] Layout validation accepts catalog entries and rejects unknown identifiers.
- [x] Published HTML resolves templates through the registry without accepting paths from content.

## Verification plan
- Registry, layout validation, HTTP, rendering, OpenAPI and frontend tests.

## Rollout and migration
- Existing `morfira/standard` and `morfira/wide` layouts remain valid.
