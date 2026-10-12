# ADR 0024: Ship one same-origin production image

- Status: Accepted
- Date: 2026-10-12

## Context
Separate frontend and backend development servers are convenient locally but complicate cookies, CSRF, routing and installation in production.

## Decision
Build Admin Studio and optimized Composer dependencies in multi-stage Docker builds, then copy both into one FrankenPHP runtime. Caddy owns `/admin/*` fallback, security headers, immutable assets and JSON access logs. The entrypoint runs database migrations before serving.

## Alternatives and constraints
- A separate frontend service was rejected for the baseline because it creates another deployment and origin boundary.
- Registry publication remains a separate explicit release decision.

## Consequences
One artifact serves complete CMS, headless APIs and Admin Studio. Image builds become heavier but deployment becomes reproducible.

## Migration impact
Development Compose is unchanged; production adopts `compose.prod.yaml`.

## Verification
Image build, Compose validation, migrations, route smoke and Playwright E2E.
