# Production distribution

## Status
Accepted

## Problem
Morfira can run in development but has no immutable production image, Admin Studio fallback, automated migration entrypoint or documented upgrade and backup path.

## Goals
- Build one production application image containing optimized PHP dependencies and Admin Studio assets.
- Serve `/admin/*`, public HTML, APIs and immutable media from the same origin.
- Run additive migrations before the server starts and expose a production Compose profile.
- Provide documented backup, restore and upgrade commands.

## Non-goals
- Publishing images to a registry, Kubernetes manifests or managed hosting automation.

## Actors and permissions
- Operators provide secrets and database credentials through environment variables.
- Public visitors and editors retain their existing HTTP permissions.

## User and system flows
1. The operator builds and starts `compose.prod.yaml` with required secrets.
2. The entrypoint runs migrations and starts FrankenPHP.
3. Direct `/admin/*` requests receive the SPA entry document while assets remain immutable.
4. Backup and restore scripts operate on explicit archive paths.

## Contracts
- The production image exposes port 8080 and stores media under `/app/back/public/media`.
- `/admin/*` is an HTML fallback; `/api`, `/_preview`, `/media` and public routes remain backend-owned.

## Data and invariants
- Images contain no runtime secrets or mutable database data.
- Database and media volumes are backed up together.

## Failure modes
- Missing required environment values fail Compose interpolation.
- A failed migration prevents server startup.
- Restore requires an explicit confirmation flag and refuses missing archives.

## Security and privacy
- Production cookies use HTTPS-aware secure mode and secrets are never committed.
- Security headers are set at the web boundary.

## Acceptance criteria
- [x] A clean production build contains PHP vendors and Admin Studio assets.
- [x] Direct `/admin/settings` resolves to Admin Studio.
- [x] Container startup applies migrations before accepting requests.
- [x] Backup, restore and upgrade procedures are executable and documented.

## Verification plan
- Docker image build, production Compose config validation, route smoke tests and shell syntax checks.

## Rollout and migration
- Existing development Compose behavior remains unchanged. Operators opt into the production overlay.
