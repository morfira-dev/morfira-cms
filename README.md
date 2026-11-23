# Morfira CMS

A lightweight, scalable and extensible content management system for websites, applications and APIs.

The project starts as a modular monolith. Symfony provides infrastructure and delivery adapters; business modules must not depend on the framework.

## Requirements

- Docker Desktop, or
- PHP 8.4+ and Composer 2

## Start with Docker

```bash
docker compose run --rm composer install
docker compose run --build --rm --no-deps front pnpm install --frozen-lockfile
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

Use Bash to back up the database and both public and private media together to a new, access-restricted directory:

```bash
./scripts/backup.sh /absolute/path/to/backup
./scripts/restore.sh --confirm-destructive-restore /absolute/path/to/backup
./scripts/upgrade.sh
```

Restore checks archive integrity before stopping the application. It then replaces the target installation's database and media and must be tested on a disposable environment before production use. Keep an off-host copy, encrypt archives containing private content, and verify the public and Admin Studio routes after every upgrade.

The isolated production-browser journey can be run with `./scripts/test-e2e.sh`; it uses a dedicated Compose project and deletes only that project's disposable volumes.

Run `bash scripts/test-backup-restore.sh` to verify how the backup and restore commands handle failed dumps, invalid archives, and SQL import errors.

See the [release upgrade and restore rehearsal](docs/verification/upgrade-restore-rehearsal.md) for the `v1.14.0` compatibility checks and the evidence to capture on a disposable target.

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

Frontend commands run through Docker with the pnpm version pinned in `front/package.json`. Release tooling uses the version pinned in the root `package.json` and a separate root `pnpm-lock.yaml`.

```bash
docker compose run --rm --no-deps front pnpm run check
```

## Releases

Releases are created automatically from Conventional Commits after a verified push to `main`.

- `feat` creates a minor release;
- `fix`, `perf` and `refactor` create a patch release;
- `break`, `feat!`, `fix!` and the `BREAKING CHANGE` footer create a major release;
- `docs`, `test`, `build`, `ci`, `chore` and `style` do not create a release by themselves.

Each release updates `CHANGELOG.md`, creates a `vX.Y.Z` tag and publishes a GitHub Release. Morfira CMS is not published to npm.

Set the `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` GitHub Actions secrets to send successful release announcements and release notes to Telegram. The bot must be able to post to the destination. The release workflow skips Telegram when either secret is missing and logs delivery failures without failing an already published release.

Validate the next release locally without publishing it:

```bash
corepack enable pnpm
pnpm install --frozen-lockfile
pnpm run release:dry-run --no-ci
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
- reusable, server-sanitized rich-text authoring for page blocks and custom content fields;
- brokerless scheduled publication for exact page revisions and entry versions;
- idempotent page and entry unpublishing that preserves immutable snapshots;
- flat or hierarchical entry taxonomies embedded in immutable public snapshots;
- taxonomy and term lifecycle management with hierarchy safeguards;
- cacheable public taxonomy archive pages built from immutable entry snapshots;
- schema-validated single and ordered multi-entry relations stored by stable identifier;
- authenticated reverse-reference navigation backed by the deletion-safe relation index;
- append-only entry revision history with optimistic restore into a new draft version;
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

Remote theme/extension installation, live-linked sections and advanced editorial workflows are intentionally deferred. They will be introduced through vertical slices and explicit ADRs.

## Import drafts from WordPress

In Admin Studio, owners can open **WordPress import**, choose a WXR export or a public WordPress REST API, and preview the report before creating drafts. Search and filter materials, inspect media, taxonomy, author, and redirect outcomes, and select which drafts to create. Use the same source key to continue with the remaining materials. Download the current report as JSON before or after applying. The REST form can import a bounded batch of public images from the source host and [explicitly allowed CDN hosts](docs/specs/wordpress-studio-rest-cdn-media.md). The CLI supports larger REST sources and authenticated WordPress access. See the [WXR specification](docs/specs/wordpress-studio-import.md), [Studio REST specification](docs/specs/wordpress-studio-rest-import.md) and [Studio image import specification](docs/specs/wordpress-studio-rest-media.md).

Studio owners can revisit completed imports in **Import history**, filter runs by source key, input format, scope and execution channel, download saved reports and open newly created drafts in a separate tab. See the [import history specification](docs/specs/wordpress-studio-import-history.md).

In **Migration sources**, find a WordPress installation by source-key prefix, see its Studio and CLI batch counts, and open either its latest report or its full run history. See the [source overview specification](docs/specs/wordpress-migration-sources.md).

After applying a batch, preview the same source again or open its filtered history from the result. A saved report can also start a new preview with its format and source key. Choose the export or enter the REST URL to continue. A fresh preview shows which materials remain eligible.

Use **Material review** in a preview, result, or saved report to find drafts affected by missing images, missing author profiles or redirect problems. Its filters combine with the material search and leave the selected drafts and JSON export intact.

Export a WXR file (1.0–1.2, up to 8 MiB and 5,000 items) from WordPress. Start the Morfira stack and apply pending migrations before using the importer. For the development stack, run:

```bash
docker compose cp /absolute/path/to/export.xml app:/tmp/wordpress-export.xml
docker compose exec -T app php bin/cms cms:import:wordpress /tmp/wordpress-export.xml --source=legacy-site
docker compose exec -T app php bin/cms cms:import:wordpress /tmp/wordpress-export.xml --source=legacy-site --apply
```

For production, use `docker compose -f compose.prod.yaml` with the installation environment. The import without `--apply` produces a JSON report without writes. With `--apply`, eligible pages and posts are created as drafts, and built-in categories and tags are assigned to newly imported posts. Keep the same source key for every export from that WordPress installation.

Add `--record-history` to `--apply` for a WXR or public REST import to save its report. Owners can find the run under **Import history** using the **Command line (CLI)** filter. Authenticated private imports keep their reports in the CLI. See the [CLI history specification](docs/specs/wordpress-cli-import-history.md).

To read public content directly over WordPress REST, use the canonical HTTPS namespace:

```bash
docker compose exec -T app php bin/cms cms:import:wordpress --api-url=https://example.com/wp-json/wp/v2 --source=legacy-site
docker compose exec -T app php bin/cms cms:import:wordpress --api-url=https://example.com/wp-json/wp/v2 --source=legacy-site --apply
```

REST mode requires PHP cURL and public access to the standard collections. Pages, posts and attachments share their WordPress IDs with WXR when the source key matches. REST apply downloads eligible images from the configured WordPress HTTPS host. Dry-run plans them from metadata without fetching image bytes.

For uploads on a CDN, repeat `--media-host` for each exact hostname:

```bash
docker compose exec -T app php bin/cms cms:import:wordpress \
  --api-url=https://example.com/wp-json/wp/v2 --source=legacy-site \
  --media-host=cdn.example.com --media-host=images.example.com
```

Use the same options with `--apply` to run the plan. The WordPress API hostname remains allowed. The list is scoped to this invocation and supports hostnames on HTTPS port 443. See the [REST import specification](docs/specs/wordpress-rest-import.md), the [download specification](docs/specs/wordpress-rest-media-download.md) and the [CDN policy](docs/specs/wordpress-rest-cdn-media.md).

To migrate restricted posts and pages, create a WordPress Application Password for an account that can edit the intended content. Export `CMS_WORDPRESS_USERNAME` and `CMS_WORDPRESS_APPLICATION_PASSWORD` in your shell, then run:

```bash
docker compose exec -T -e CMS_WORDPRESS_USERNAME -e CMS_WORDPRESS_APPLICATION_PASSWORD \
  app php bin/cms cms:import:wordpress --api-url=https://example.com/wp-json/wp/v2 \
  --source=legacy-site --include-private
```

Without `--apply` this is a dry-run in Morfira. Apply stores restricted items as drafts without imported redirects. Supported private image attachments are re-encoded into the private media volume and are available with a Morfira editor or owner session. In Admin Studio, filter the media library by visibility to find these images. The page and entry editors flag private selections that must be replaced or removed before publishing. Authenticated file downloads require a same-host HTTPS URL that accepts the Application Password. An explicitly supplied local uploads copy is also supported. See the [authenticated import specification](docs/specs/wordpress-rest-private-import.md), [private attachment specification](docs/specs/wordpress-rest-private-media.md) and [WordPress Application Password documentation](https://developer.wordpress.org/advanced-administration/security/application-passwords/).

For WXR attachments or to use a local copy with REST, mount a trusted read-only WordPress uploads tree into the application container and pass `--media-dir=/path/to/uploads`. Supported JPEG, PNG and WebP attachments are added to the media library as WebP variants. Imported drafts receive inline image references in content order. A plain-text caption on a resolved single-image figure can travel with its image block on a page. Featured images appear in page Open Graph settings or the dedicated post media field. Review unresolved links in the JSON report before publishing. See the [image caption specification](docs/specs/wordpress-image-captions.md).

WordPress authors become content profiles and new posts retain author attribution and a profile relation. For published WordPress items, supported old URLs are staged as permanent redirects and become active when the new drafts are published in Morfira. Absolute WordPress links in newly imported drafts can resolve to Morfira routes from the current batch or earlier batches with recorded targets under the same source key. See the [internal link specification](docs/specs/wordpress-internal-links.md).

The importer keeps existing drafts unchanged on replay. Attachment mappings created before image binding support have no target UUID and cannot be reused automatically. See the [draft import](docs/specs/wordpress-wxr-import.md), [media and taxonomy](docs/specs/wordpress-wxr-media-taxonomy.md), [image binding](docs/specs/wordpress-wxr-image-bindings.md) and [authors and redirects](docs/specs/wordpress-wxr-authors-redirects.md) specifications.

Gutenberg import preserves supported headings, buttons, quotes, details, verse, tables, code, static media links, and rendered navigation in Morfira's existing text and image blocks. Archive and category dropdowns with explicit URLs become link lists. Classic galleries with explicit attachment IDs and legacy gallery image IDs reuse the existing attachment index. Review the imported content for layout and playback differences. See the [Gutenberg block specification](docs/specs/wordpress-gutenberg-blocks.md).

## Architecture

See [docs/architecture.md](docs/architecture.md) and [docs/adr](docs/adr).

## License

Morfira CMS is open-source software released under the [MIT License](LICENSE).

Commercial products and proprietary modules may use the MIT-licensed core in accordance with its terms. Third-party dependencies retain their own licenses and notice requirements; see [docs/dependencies/licenses.md](docs/dependencies/licenses.md).
