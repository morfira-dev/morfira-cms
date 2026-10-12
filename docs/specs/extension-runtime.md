# Extension execution runtime

## Status
Implemented

## Problem
Morfira has no stable way for separately packaged modules to declare capabilities or participate in controlled application hooks.

## Goals
- Define versioned extension manifests, capabilities and synchronous typed hooks.
- Expose installed extension status to owners.
- Isolate extension failures and keep core behavior deterministic.

## Non-goals
- Uploading or evaluating code, dependency installation, sandboxing untrusted PHP, a marketplace or remote callbacks.

## Actors and permissions
- Operators install extensions through Composer/deployment and service configuration.
- Owners inspect the installed catalog; editors cannot change executable code.

## User and system flows
1. Trusted services register manifests and hook subscribers at container build time.
2. Core application services dispatch immutable hook context through the extension bus.
3. Subscribers return typed contributions or diagnostics within their declared capability.
4. Owners inspect enabled extensions and compatibility through Admin Studio/API.

## Contracts
- `GET /api/v1/admin/extensions` is owner-only and returns identifier, version, provider, capabilities and status.
- Runtime contracts use a major API version independent from package versions.

## Data and invariants
- Extension identifiers are reverse-domain slugs and unique.
- Capabilities are allow-listed; registration fails closed on incompatible API versions.
- Hooks cannot replace persistence transactions or mutate published snapshots in place.

## Failure modes
- Duplicate, incompatible or unknown capabilities fail container startup.
- Runtime subscriber exceptions are converted to bounded diagnostics and cannot expose secrets.

## Security and privacy
- Only trusted deployment code executes. No admin endpoint accepts source, package URLs or executable configuration.

## Acceptance criteria
- [x] A built-in example extension registers a manifest and typed page-published observer.
- [x] Owners can list compatible enabled extensions; editors receive `403`.
- [x] Invalid manifests fail registration and subscriber failures do not corrupt publication.

## Verification plan
- Contract, registry, bus, authorization, OpenAPI, frontend and architecture tests.

## Rollout and migration
- No persistence migration; the initial registry is container-backed.
