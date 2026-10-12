# Morfira CMS

A lightweight, scalable and extensible content management system for websites, applications and APIs.

The project starts as a modular monolith. Symfony provides infrastructure and delivery adapters; business modules must not depend on the framework.

## Requirements

- Docker Desktop, or
- PHP 8.4+ and Composer 2

## Start with Docker

```bash
docker compose run --rm composer install
docker compose run --rm front npm install
docker compose up app front
```

Open the Admin Studio at <http://localhost:5174>, the backend health endpoint at <http://localhost:8080/health>, and the OpenAPI 3.1 contract at <http://localhost:8080/api/openapi.yaml>. Override the frontend port with `CMS_FRONT_PORT` when needed. Set `CMS_PUBLIC_SITE_URL` to the external site origin in deployments so canonical, Open Graph, robots and sitemap URLs are absolute and correct.

## Production deployment

The production image contains the optimized backend and Admin Studio and serves both from one origin. Provide secrets outside source control, then start the production stack:

```bash
export CMS_APP_SECRET='replace-with-a-long-random-secret'
export CMS_DATABASE_PASSWORD='replace-with-a-database-password'
export CMS_DATABASE_ROOT_PASSWORD='replace-with-a-root-password'
export CMS_PUBLIC_SITE_URL='https://cms.example.com'
docker compose -f compose.prod.yaml up -d --build
```

Open `/admin`; `/health` is the dependency-free liveness endpoint and `/ready` includes database readiness. Startup applies pending migrations before accepting traffic. Set `CMS_MAINTENANCE_MODE=1` and recreate the app container to return controlled `503` responses while preserving health checks.

Back up database and media together to a new, operator-controlled directory:

```bash
./scripts/backup.sh /absolute/path/to/backup
./scripts/restore.sh --confirm-destructive-restore /absolute/path/to/backup
./scripts/upgrade.sh
```

Restore replaces the target installation's database and media and must be tested on a disposable environment before production use. Keep an off-host copy, encrypt archives containing private content, and verify the public and Admin Studio routes after every upgrade.

The isolated production-browser journey can be run with `./scripts/test-e2e.sh`; it uses a dedicated Compose project and deletes only that project's disposable volumes.

## Project structure

```text
back/          PHP 8.4+, Symfony 8.1 and CMS modules
front/         React, TypeScript and the MUI-based Admin Studio
docs/          Architecture and decision records
compose.yaml   Shared local development environment
```

## Local commands

```bash
cd back
composer install
composer check
```

Frontend commands run through Docker:

```bash
docker compose run --rm --no-deps front npm run check
```

## Releases

Releases are created automatically from Conventional Commits after a verified push to `main`.

- `feat` creates a minor release;
- `fix`, `perf` and `refactor` create a patch release;
- `break`, `feat!`, `fix!` and the `BREAKING CHANGE` footer create a major release;
- `docs`, `test`, `build`, `ci`, `chore` and `style` do not create a release by themselves.

Each release updates `CHANGELOG.md`, creates a `vX.Y.Z` tag and publishes a GitHub Release. Morfira CMS is not published to npm.

Validate the next release locally without publishing it:

```bash
npm run release:dry-run
```

## Current scope

The initial foundation contains:

- HTTP and CLI entry points;
- a first vertical slice (`SystemStatus`);
- module-first source layout;
- framework-boundary architecture test;
- static analysis and unit/functional test configuration;
- Docker and FrankenPHP development runtime;
- React, TypeScript and MUI-based Admin Studio foundation;
- MariaDB persistence and the first `Page` draft slice;
- ordered `Hero` and `RichText` blocks, immutable revisions and optimistic locking;
- server-rendered Twig draft preview protected with `noindex` and `no-store`;
- idempotent publication into immutable delivery snapshots;
- cacheable public Twig HTML and headless `/api/v1/content` delivery with ETags;
- atomic first-owner setup, session authentication, editor roles and CSRF protection;
- immutable image assets with safe WebP re-encoding, responsive variants and reusable Image blocks;
- searchable media collections and tags, mutable alt metadata and reference-safe asset deletion;
- revisioned SEO metadata, canonical/Open Graph output, `robots.txt` and a published-snapshot sitemap;
- permanent slug history with direct `301` redirects and an optional CMS-managed `404` page;
- versioned three-level navigation trees with optimistic editing and immutable public snapshots;
- versioned built-in page layouts with published header and footer navigation placement;
- versioned custom content schemas, schema-validated entry drafts and immutable public entry snapshots;
- brokerless scheduled publication for exact page revisions and entry versions;
- idempotent page and entry unpublishing that preserves immutable snapshots;
- flat or hierarchical entry taxonomies embedded in immutable public snapshots;
- taxonomy and term lifecycle management with hierarchy safeguards;
- cacheable public taxonomy archive pages built from immutable entry snapshots;
- schema-validated single-entry relations stored by stable identifier;
- safe draft deletion with database-enforced relation integrity;
- manually managed direct permanent redirects for active page routes;
- owner-managed site identity and regional defaults with optimistic locking;
- visible immutable page revision history and append-only draft restoration;
- locale-aware page variants grouped as translations, with stable default and prefixed public URLs, canonical links and `hreflang` alternates;
- a visual page canvas with selection, drag-and-drop ordering, keyboard move controls, duplication and removal;
- deployment-trusted theme packages with catalog-backed template selection and two bundled themes;
- versioned reusable sections that insert independent block copies into page drafts;
- a versioned, container-registered extension API with declared capabilities, typed publication hooks and owner-visible diagnostics;
- authenticated self-service password rotation;
- a live Admin Studio content overview with direct workspace navigation;
- a single-image production deployment, readiness/maintenance controls, backup and restore scripts, and a production browser journey;
- versioned `/api/v1` contracts with frontend types generated from OpenAPI 3.1;
- architectural decision records.

Multi-entry and reverse relation editing, rich HTML fields, remote theme/extension installation, live-linked sections and advanced editorial workflows are intentionally deferred. They will be introduced through vertical slices and explicit ADRs.

## Architecture

See [docs/architecture.md](docs/architecture.md) and [docs/adr](docs/adr).

## License

Morfira CMS is open-source software released under the [MIT License](LICENSE).

Commercial products and proprietary modules may use the MIT-licensed core in accordance with its terms. Third-party dependencies retain their own licenses and notice requirements; see [docs/dependencies/licenses.md](docs/dependencies/licenses.md).
