# Public beta verification

Use a tagged build and a disposable installation with synthetic content. Record the tag or commit, deployment mode, browser and verification date. The test installation can serve the public site and headless API from the same origin.

## Tester journey

The [Chromium production journey](../../front/e2e/critical-journey.spec.ts) exercises setup, draft isolation, republication, Studio language persistence and WordPress import in CI.

| Task | Expected result |
| --- | --- |
| Start the [production stack](../../README.md#production-deployment) and open `/admin/`. | `/ready` returns HTTP 200 and Studio offers initial owner setup on a fresh database. |
| Create an owner, sign in, create a page and save a draft. | The saved content survives reload. An anonymous request to the planned URL does not expose the draft. |
| Publish the page, then open it signed out in a separate browser session. Request `GET /api/v1/content/pages/{slug}` from the same origin, substituting the published slug. | Public HTML and the [headless API](../../back/openapi/openapi.yaml) both expose the published revision. A later draft edit stays private until republished. |
| Switch the Studio language to Russian, sign out and sign in again. Keep the website language unchanged during this step. | Studio remembers Russian for that account. The published site's locale and URL are unchanged. |
| [Rehearse one WordPress import](wordpress-migration-rehearsal.md) with a synthetic export. Run a preview, apply it, then run it again with the same source key. | Preview leaves the installation unchanged. Apply creates drafts, and the repeat does not create duplicate drafts. |

Report product defects through the [bug report form](https://github.com/morfira-dev/morfira-cms/issues/new?template=bug.yml). Include the failed step, the tested release or commit, and the expected and actual result. For security concerns, follow the [security policy](../../SECURITY.md).

## Before announcing a candidate

1. Run `node scripts/verify-release-tag.mjs vX.Y.Z` with the release tag and its parent available in a local checkout. The output identifies the tagged commit and its candidate parent after checking the changelog and the OpenAPI version bump.
2. Record the passing GitHub Actions **Verify candidate** run for the reported candidate commit. Run the tester journey from the exact tagged release on a disposable installation. Record any blocking issue against that tag.
3. Review the [upgrade and restore rehearsal](upgrade-restore-rehearsal.md) and repeat it for the candidate when persistence or production startup changes.
4. Confirm that the default branch serves the bug report form and that **Report a vulnerability** is available to a signed-in non-maintainer. GitHub provides a [private reporting setting](https://docs.github.com/en/code-security/how-tos/report-and-fix-vulnerabilities/configure-vulnerability-reporting/configure-for-a-repository) for public repositories.

## Rehearse an exact tag

Run these commands from the repository root in Bash. The temporary directory holds the source and browser reports. The temporary Compose project listens on loopback. Choose a free port and use it in both environment values. The commands stop the stack and retain its isolated volumes for inspection.

```bash
set -e -o pipefail
release_tag=v1.14.0
release_commit=$(git rev-parse --verify "refs/tags/${release_tag}^{commit}")
release_tree=$(mktemp -d)
node scripts/verify-release-tag.mjs "$release_tag" > "$release_tree/release-identity.json"
git archive "$release_commit" | tar -xf - -C "$release_tree"

(
  cd "$release_tree"
  project_name="morfira-tagged-$(openssl rand -hex 4)"
  export CMS_APP_SECRET="$(openssl rand -hex 32)"
  export CMS_DATABASE_PASSWORD="$(openssl rand -hex 24)"
  export CMS_DATABASE_ROOT_PASSWORD="$(openssl rand -hex 24)"
  export CMS_PUBLIC_SITE_URL=http://localhost:18183 CMS_PORT=127.0.0.1:18183
  compose() {
    docker compose -p "$project_name" -f compose.prod.yaml -f compose.e2e.yaml "$@"
  }
  trap 'compose stop app database' EXIT

  mkdir -p front/test-results front/playwright-report
  compose build app e2e
  compose up -d --no-build --wait app
  curl -fsS "$CMS_PUBLIC_SITE_URL/ready" > ready.json
  curl -fsS "$CMS_PUBLIC_SITE_URL/api/openapi.yaml" > served-openapi.yaml
  cmp back/openapi/openapi.yaml served-openapi.yaml
  curl -fsS "$CMS_PUBLIC_SITE_URL/api/v1/setup" > initial-setup.json
  jq -e '.required == true' initial-setup.json >/dev/null
  compose run --rm -T --no-deps e2e pnpm run test:e2e --workers=1
)
```

The tag determines which browser scenarios are available. Use the tester journey above for checks introduced after that tag. Report a clean image build separately from a runtime rehearsal that used prebuilt base images or mounted assets.

## Recorded release rehearsal

On 2026-11-23, `v1.14.0` at `5ce249e` was exported with `git archive`. The production and Chromium images were built from its Dockerfiles on a clean arm64 Docker installation. The stack used PHP 8.4.28 and MariaDB 11.4.13.

| Check | Result |
| --- | --- |
| Production and Chromium images built from the tag | Passed |
| `/ready` and initial setup on a fresh database | Passed |
| Served OpenAPI matched the tagged file byte for byte | Passed |
| Tagged Chromium suite | 5 passed |

The suite exercised Admin Studio entry and draft links, the initial request flow, page editing and publication through HTML and the headless API, and WordPress import and history in Studio. This result applies to the tests and product code shipped in `v1.14.0`.
