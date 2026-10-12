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
- revisioned SEO metadata, canonical/Open Graph output, `robots.txt` and a published-snapshot sitemap;
- permanent slug history with direct `301` redirects and an optional CMS-managed `404` page;
- versioned three-level navigation trees with optimistic editing and immutable public snapshots;
- versioned custom content schemas, schema-validated entry drafts and immutable public entry snapshots;
- brokerless scheduled publication for exact page revisions and entry versions;
- flat or hierarchical entry taxonomies embedded in immutable public snapshots;
- schema-validated single-entry relations stored by stable identifier;
- versioned `/api/v1` contracts with frontend types generated from OpenAPI 3.1;
- architectural decision records.

Multi-entry and reverse relations, localization, rich HTML fields, deletion, taxonomy editing/deletion and public term archive pages, menu placement/theme bindings, manual redirect editing, extension/plugin execution and advanced editorial workflows are intentionally deferred. They will be introduced through vertical slices and explicit ADRs.

## Architecture

See [docs/architecture.md](docs/architecture.md) and [docs/adr](docs/adr).
