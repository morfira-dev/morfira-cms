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
