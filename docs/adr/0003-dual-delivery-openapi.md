# ADR 0003: Full CMS and headless API share one core

## Status

Accepted.

## Decision

The product supports two first-class delivery modes:

1. server-rendered public HTML for a complete CMS installation;
2. a versioned HTTP API for headless and integration use cases.

Admin Studio is an API client and must not receive a private backdoor into persistence. Public HTML and HTTP API use the same application contracts but have separate presentation adapters.

OpenAPI 3.1 is the source of truth for the HTTP contract. Frontend API types are generated from the committed schema. Breaking contracts require a new API version or an explicit migration policy.

## Consequences

- `/api/v1` remains stable within its major version.
- `/api/v1/admin` serves authenticated authoring; `/api/v1/content` serves published snapshots only.
- Public HTML rendering can evolve without coupling templates to API response shapes.
- Domain and application code stay independent from HTTP, React and Twig.
- Contract drift is detected by generated types and automated validation.
