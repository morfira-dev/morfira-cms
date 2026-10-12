# Morfira CMS agent instructions

These instructions apply to the entire repository. This file is the canonical source of repository-wide agent guidance. Tool-specific instruction files must stay thin and point back here rather than duplicate these rules.

## Mandatory bootstrap

Before planning, reviewing, or editing:

1. Read this file completely.
2. Read `docs/agents/SKILLS.md` completely and select the relevant workflow.
3. Read `docs/agents/SDD.md` completely and decide whether the task requires a written specification.
4. Read the relevant ADRs, manifests, tests, and nearby implementation before proposing changes.
5. Inspect `git status` and preserve unrelated work.

If an instruction file changes during a session, reload it before continuing.

## Language

- Write code, identifiers, comments, commit messages, release notes, ADRs, specifications, and repository documentation in English.
- User-facing conversation may follow the user's language.
- Use Conventional Commits in English.

## Product and stack

Morfira is a lightweight, scalable CMS that supports both complete server-rendered websites and headless API delivery.

- Backend: PHP 8.4+, Symfony, Doctrine, Twig.
- Admin Studio: React, TypeScript, MUI, Vite.
- Contract: OpenAPI 3.1 under `back/openapi/openapi.yaml`.
- Persistence: MariaDB in local Docker; keep persistence boundaries database-portable where practical.
- Local environment and verification: Docker Compose.

Repository layout:

- `back/`: backend modules, public delivery, migrations, tests, and OpenAPI.
- `front/`: Admin Studio and generated API types.
- `docs/adr/`: accepted architectural decisions.
- `docs/agents/`: agent workflows and specification-driven development rules.

## Architecture invariants

- Keep a modular monolith organized by business capability.
- Dependencies point inward: Presentation -> Application -> Domain; Infrastructure implements inward-facing ports.
- Domain code must not depend on Symfony, Doctrine, HTTP, Twig, queues, or a concrete database.
- Do not put business logic in controllers, console commands, templates, or React view components.
- Modules communicate through explicit application contracts or domain events, never cross-module persistence access.
- Preserve the composition model `Page -> Region -> Block -> Component`; never implement it with nested HTTP requests.
- Drafts and revisions are mutable authoring concerns. Public HTML and `/api/v1/content` read immutable published snapshots only.
- Keep complete CMS and headless delivery as equal, first-class surfaces over the same application core.
- Do not add mandatory Redis, brokers, search servers, or microservices to the small-installation baseline.

## API and generated files

- Treat `back/openapi/openapi.yaml` as the authoritative HTTP contract.
- Keep `/api/v1` backward compatible within the major version. Breaking API changes require an explicit migration/versioning decision.
- After changing OpenAPI, regenerate and commit `front/src/api/schema.d.ts` with `npm run api:types` in the frontend container.
- Do not edit `front/src/api/schema.d.ts` manually.
- Do not track or edit `back/config/reference.php`; Symfony generates it per environment and it is ignored.

## Security and data

- Never commit credentials, tokens, private keys, production secrets, or real personal data.
- Preserve server-side sessions, CSRF protection for mutating requests, editor authorization for admin/preview, and anonymous cacheable public delivery.
- Validate input at trust boundaries and keep authorization server-side.
- Preserve safe media decoding/re-encoding and immutable asset behavior.
- Never invent initial owner credentials or weaken setup/login protections for convenience.

## Licensing and dependencies

- Morfira community core is licensed under the root MIT `LICENSE`; keep npm and Composer manifest metadata synchronized with it.
- Before adding or updating a dependency, verify its license, commercial-use terms, redistribution duties, maintenance status, and known security advisories.
- Do not introduce copyleft, source-available, non-commercial, custom, or unknown licenses without an explicit product decision.
- Record reviewed exceptions in `docs/dependencies/licenses.md` and keep `scripts/check-licenses.mjs` fail-closed for new package licenses, container images, and GitHub Actions.
- Run `npm run licenses:check` after every dependency or delivery-image change.
- Keep proprietary commercial modules in separately licensed packages or repositories; copied MIT core code must retain the MIT notice.

## Implementation workflow

- Prefer one coherent vertical slice over broad layer-first scaffolding.
- Characterize existing behavior and contracts before refactoring.
- Update tests, OpenAPI, generated types, migrations, documentation, and ADRs in the same change when their contracts move together.
- Add an ADR when a change introduces or reverses a durable architectural decision.
- Use Docker for application dependency installation, generation, tests, and analysis.
- Do not edit vendor, build, cache, or runtime output.

## Verification

Run the narrowest relevant checks while iterating, then the full affected gates before handoff or commit.

Backend:

```bash
docker compose run --rm --no-deps composer check
```

Frontend:

```bash
docker compose run --rm --no-deps front npm run check
```

Release configuration:

```bash
npm run release:dry-run -- --no-ci
```

Also run `git diff --check`. Do not describe a check as passing if it was skipped, blocked, or only partially executed.

## Git and releases

- Preserve unrelated local changes.
- Do not commit, push, force-push, tag, publish, or open a pull request unless the user explicitly authorizes it.
- Commit types: `feat`, `fix`, `perf`, `refactor`, `docs`, `test`, `build`, `ci`, `chore`, `style`, `revert`, and `break`.
- `break:` is the Morfira shorthand for a major release. Standard `type!:` and `BREAKING CHANGE:` footer syntax remain supported.
- Release-triggering behavior is defined in `.releaserc.json`; keep `README.md` synchronized with it.

## Definition of done

A change is done only when its acceptance criteria are met, architectural boundaries remain intact, relevant tests and generated contracts are updated, full affected gates pass, the diff contains no secrets or unrelated edits, and documentation describes any new durable behavior.
