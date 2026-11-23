# Release upgrade and restore rehearsal

## Starting point

The 2026-11-23 rehearsal used `v1.14.0` at `5ce249e8`, then upgraded the application to the working tree at `7c7294ea`. The database started on MariaDB 11.4.13 and remained on that version for both paths.

The release and candidate mounted their respective backend source, configuration, migrations, templates, OpenAPI and runtime entrypoint on an existing FrankenPHP image. Their Composer lockfiles matched. This setup exercised migrations and HTTP surfaces with the production entrypoint. It left rebuilding a fresh production image and checking Studio assets to the separate build and browser verification.

Docker's writable filesystem was full during the initial named-volume startup. The successful setup used two isolated Compose projects and networks, independent MariaDB instances on tmpfs, and separate host directories for public and private media. The in-place upgrade kept the original database container running. The restore target started with an empty database and empty media directories.

## Fixture and backup

The release started with fresh synthetic data. An owner signed in through the CSRF-protected API, imported a WXR fixture from Studio's endpoint, and published one page. A public image was uploaded over the media API. The restricted REST import fixture created private drafts and an authenticated image variant. The test recorded the owner UUID, draft UUIDs and titles, published revision, import claims, and both image response bodies.

The current `scripts/backup.sh` created `database.sql.gz`, `media.tar.gz`, `private-media.tar.gz`, and `manifest.txt` while the release was running. All archives passed validation. The manifest contained the release commit. The same pre-upgrade backup served as the recovery point for the separate restore target.

## Upgrade and restore checks

| Check | In-place application upgrade | Restore to the candidate |
| --- | --- | --- |
| History channel and source index migrations applied | Passed | Passed |
| Existing Studio run available in the source overview | Passed | Passed |
| Owner could sign in and retained the same UUID | Passed | Passed |
| Draft identifiers and titles matched the fixture | Passed | Passed |
| Published HTML and headless page retained the expected content and revision | Passed | Passed |
| WordPress import ledger retained the same claim counts | Passed | Passed |
| Public and authenticated private WebP responses matched the baseline byte for byte | Passed | Passed |
| Anonymous request to the private asset returned HTTP 401 | Passed | Passed |

## Repeat the rehearsal

Use separate release and candidate checkouts. Export `CMS_APP_SECRET`, `CMS_DATABASE_PASSWORD`, and `CMS_DATABASE_ROOT_PASSWORD` for the disposable stacks. Set the paths below to your checkouts and a new backup directory.

```bash
release_tree=/absolute/path/to/release-checkout
candidate_tree=/absolute/path/to/candidate-checkout
backup_dir=/absolute/path/to/new-rehearsal-backup

export COMPOSE_PROJECT_NAME=morfira-rehearsal-source
export CMS_PORT=18178 CMS_PUBLIC_SITE_URL=http://localhost:18178
cd "$release_tree"
docker compose -f compose.prod.yaml up -d --build --wait
```

Seed the fixture and record the response bodies and identifiers listed above. Stop fixture writes before capturing the backup. Run the candidate backup script from the release checkout so the manifest identifies the backed-up release.

```bash
"$candidate_tree/scripts/backup.sh" "$backup_dir"
gzip -t "$backup_dir/database.sql.gz"
tar -tzf "$backup_dir/media.tar.gz" >/dev/null
tar -tzf "$backup_dir/private-media.tar.gz" >/dev/null

cd "$candidate_tree"
docker compose -f compose.prod.yaml up -d --build --no-deps --wait app
curl --fail --silent --show-error "$CMS_PUBLIC_SITE_URL/ready"
```

Compare the upgraded application with the recorded release responses. For recovery, pick a new Compose project name and port. Confirm the project has an empty database and empty media volumes before running the restore command.

```bash
export COMPOSE_PROJECT_NAME=morfira-rehearsal-recovery
export CMS_PORT=18179 CMS_PUBLIC_SITE_URL=http://localhost:18179
docker compose -f compose.prod.yaml build app
docker compose -f compose.prod.yaml up -d --wait database
./scripts/restore.sh --confirm-destructive-restore "$backup_dir"
docker compose -f compose.prod.yaml up -d --wait app
curl --fail --silent --show-error "$CMS_PUBLIC_SITE_URL/ready"
```

Compare the recovered application with the same release responses. Check the private image both with and without an owner session. Review the backup and restore failure behavior in [Production operations](../specs/production-operations.md).
