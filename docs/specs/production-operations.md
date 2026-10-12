# Production operations

## Status
Accepted

## Problem
Liveness alone cannot tell an orchestrator whether the database is ready, and the application lacks a controlled maintenance response and production boundary policy.

## Goals
- Keep `/health` dependency-free and add database-backed `/ready`.
- Provide environment-controlled maintenance mode with deterministic `503` responses.
- Emit structured JSON access logs and security headers through Caddy.
- Document migration, backup and recovery operations.

## Non-goals
- External observability vendors, metrics storage and distributed tracing.

## Actors and permissions
- Orchestrators call health endpoints; operators control maintenance mode through environment configuration.

## User and system flows
1. `/health` reports process liveness.
2. `/ready` verifies the database and returns `503` on failure.
3. Maintenance mode preserves health/readiness while other requests receive `503` and `Retry-After`.

## Contracts
- `GET /ready` returns `{status, service, database}`.
- Maintenance responses are JSON for API requests and HTML for browser requests.

## Data and invariants
- Health checks never expose connection strings or exception details.

## Failure modes
- Database errors produce sanitized readiness output and structured server logs.

## Security and privacy
- Logs exclude request bodies, cookies and authorization headers.
- Browser responses receive frame, content-type, referrer and permissions policies.

## Acceptance criteria
- [x] Liveness works without a database query.
- [x] Readiness reflects database availability.
- [x] Maintenance mode exempts health endpoints and blocks normal traffic.
- [x] Production Caddy emits JSON access logs and security headers.

## Verification plan
- Application and WebTestCase coverage, Caddy validation and production-container smoke tests.

## Rollout and migration
- Maintenance mode defaults off; readiness is additive.
