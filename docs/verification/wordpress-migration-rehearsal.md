# WordPress migration rehearsal

A fixture run verifies Morfira’s migration behavior. A rehearsal on a frozen export or a private staging copy of one WordPress site establishes what that site can migrate and what still needs editorial review. Record the source key, input format, snapshot timestamp, and Morfira commit so the results can be reproduced.

## Automated baseline

Run the production-browser and CLI journeys from the repository root. The test runner uses the disposable `morfira-e2e` Compose project and replaces its test volumes.

```bash
CMS_TEST_WORDPRESS_IMPORT=1 ./scripts/test-e2e.sh
```

| Check | Existing evidence |
| --- | --- |
| Content and replay | The [CLI journey](../../scripts/test-wordpress-import.sh) plans and imports the WXR page `451` and post `452`, checks their drafts and ledger claims, and reruns with changed source content to verify that titles, revisions, media mappings, and redirect assignments remain stable. Injected write failures roll back the batch. |
| Attachments, taxonomy, and authors | The same journey checks two imported WXR images, one rejected attachment, image order, alt text, captions, featured associations, parent/child terms and post assignments, and attribution without account creation. Public REST and restricted REST scenarios cover CDN options, private-image authorization, and reuse. |
| Redirects and links | `/?page_id=451` and `/2026/11/wp-import-news/` return `404` before publishing, a direct `301` to `/wp-import-about` and `/content/wordpress-posts/wp-import-news` after publishing, and `404` after unpublishing. HTML and headless assertions check local anchors, preserved fragments, and retained external or unresolved URLs. |
| Batches and reports | The [Studio browser journey](../../front/e2e/critical-journey.spec.ts) checks selection, report download, draft links, and continuation across batches. A later draft rewrites a link to an earlier batch; a forward link stored in the earlier draft retains the original WordPress URL. The CLI journey also checks saved reports and the combined source overview. |
| Restricted content | The [REST fixture](../../back/tests/Content/Fixtures/wordpress-rest-import.php) simulates public and authenticated WordPress responses with controlled fixtures. The CLI journey verifies that restricted drafts stay private, assets are inaccessible anonymously, and the CLI report omits credentials and protected content. The saved WXR report also excludes protected titles. |

These fixtures use a real Morfira stack and MariaDB. The PHP REST fixture substitutes the WordPress response and media transport. Live WordPress DNS, TLS, REST pagination, and plugin behavior require a separate rehearsal against the actual source configuration.

## Native WordPress fixture result

The [rehearsal fixture](../../scripts/fixtures/wordpress-rehearsal/README.md) provisions WordPress 7.1.5 and captures an export, original uploads, and six public REST collections from that installation. The run against Morfira `7c7294e` produced the following results.

| Check | Observed |
| --- | --- |
| WXR inventory and apply | Four pages and three posts all appeared in the dry-run and were imported as drafts. One image, two author profiles, three terms and four taxonomy assignments were imported. |
| WXR replay | All seven materials, the image and both author profiles were already imported. The independent inventory audit passed. |
| Public REST capture | The capture contained five published materials and one attachment. An initial dry-run planned five materials. Applying the capture after WXR under the same source key reused all five material IDs and the attachment. It created two additional author profiles. |
| Publication | Three representative legacy paths returned direct `301` responses to destinations returning `200`. The page and post headless payloads resolved. The unpublished post stayed `404`. The image URL responded with `200`, and the rendered image caption, alt text and Gutenberg quotation were present. |
| Internal links and fragments | The WXR item's canonical path and the body’s `?page_id=` alias differed. The imported body retained the WordPress alias links. The target heading text survived while `id="read-more"` was absent in Morfira HTML. |

### Rehearsal after the fixes

Re-running the same export on a fresh MariaDB target imported seven materials. WXR replay reused all seven. Replay of the five public materials from REST reused the image and the same two author profile IDs as WXR. After publication, the About page linked to `/rehearsal-guide#read-more`, which resolved to the retained heading ID. The Guide and News items linked back to `/rehearsal-about`. The three legacy paths still returned direct `301` responses. The Studio browser journey also verified the heading ID after editing and saving a new revision.

Existing draft revisions and author profile records retain their content and associations. Replaying an older WXR export with author IDs can establish the shared mapping for previously imported authors. The original migration and its remediation both used synthetic source content. Verify the same cases on the first pilot site.

The [REST replay helper](../../scripts/fixtures/wordpress-rehearsal/replay-rest.php) reads the captured first page of each public collection in a disposable Morfira container. Copy `inventory.json`, `rest/` and `uploads/` into a local container directory and copy the helper into the container. For example, from the application working directory:

```bash
MORFIRA_REHEARSAL_CAPTURE=/tmp/wordpress-rehearsal \
  php /tmp/replay-rest.php --api-url=https://wordpress.example/wp-json/wp/v2 \
  --source=wordpress-native-rehearsal --media-dir=/tmp/wordpress-rehearsal/uploads
```

This exercises Morfira's REST reader, mapper and import workflow with native WordPress payloads. The helper substitutes only the test transport and uses copied uploads. Live acceptance of the source through the production HTTPS transport remains a separate check.

## Source-to-destination rehearsal

Use disposable Morfira staging and keep publication tests on that staging origin. Take a backup and verify restoration separately using the [production operations instructions](../../README.md#production-deployment). Record the snapshot used for the rehearsal. Keep source data, raw reports, and any private media in access-controlled storage.

| Phase | Evidence and acceptance |
| --- | --- |
| Inventory | Capture WordPress page and post IDs, status, permalink, media IDs, and any plugin-specific types from the frozen source. Track protected items by ID without copying their titles or body into the shared evidence. Use the same source key on every run against this installation. |
| Plan | Save the dry-run JSON. Compare its `items` with the inventory by WordPress ID and type, accounting for content intentionally excluded by the selected access mode. Reconcile `skippedTypes` separately. Record each invalid or unsupported row with a disposition. Compare attachment outcomes separately from material outcomes. Before apply, confirm the planned drafts and their legacy redirects are absent from an initially empty target. |
| Apply | Save each apply report. Count unique imported `(source key, WordPress ID)` pairs across batches. Compare imported drafts and selected content by ID, destination path, and the Studio links where available. Compare actual associated image, taxonomy, and author records with the intended source associations. |
| Replay | Dry-run the entire unchanged source under the same key. Previously imported items must be `already_imported`. Any remaining `planned` item requires an explanation, such as an intentionally deferred batch. When staging permits apply replay, compare draft IDs, revisions, media IDs, and redirect targets before and after. |
| Publish on staging | Publish the agreed public sample. For each supported legacy URL, check status `301`, the exact `Location`, and a final destination that responds directly with `200`. Check at least one query permalink and one path permalink if the source uses both. Check HTML and the headless route. Leave protected and deliberately unpublished materials out of this sample and verify they are unavailable anonymously. |
| Review | Check rewritten anchors, including the existence of each expected target fragment. Review same-site links left on WordPress and links to batches published later. Open representative pages and posts for layout, captions, alt text, article attribution, mobile rendering, and SEO metadata. Review shortcodes, dynamic blocks, embeds, forms, and plugin-specific data against the [conversion contract](../specs/wordpress-gutenberg-blocks.md). |

Use the original WordPress inventory for redirect probes. Import reports can show a source path without its query string, so the report alone cannot reconstruct every legacy permalink. Use the [internal link contract](../specs/wordpress-internal-links.md) to classify links intentionally retained, and record the ones that still require an editorial fix.

Probe the legacy path and destination on Morfira staging individually. An additional redirect at the destination is visible in this check.

```bash
curl --silent --show-error --output /dev/null --write-out '%{http_code} %{redirect_url}\n' "$staging_url$legacy_path"
curl --silent --show-error --output /dev/null --write-out '%{http_code} %{redirect_url}\n' "$staging_url$destination_path"
```

## Evidence to retain

Store the inventory, dry-run, each batch report, final replay, and an exceptions list. For one whole-source WXR rehearsal on a fresh staging installation, run the independent inventory audit against the unchanged export and the three CLI reports. It checks coverage, item counts, unique draft outcomes, and replay stability. The JSON lists media and redirect diagnostics for review. Repeat `--allow-skipped WORDPRESS_ID` for each unsupported page or post acknowledged in the exceptions list.

```bash
python3 scripts/fixtures/wordpress-rehearsal/audit.py \
  wordpress-export.xml rehearsal-dry-run.json rehearsal-apply.json rehearsal-replay.json \
  > rehearsal-audit.json
```

The audit exits `0` when the reports reconcile, `1` when it finds discrepancies, and `2` when it cannot read the inputs. Use the full matrix for selection batches or REST because their inputs and coverage differ from a whole-source WXR run.

Use this format for a shared result summary. In the notes, link to the evidence or record the reason a check was skipped.

| Check | Expected | Observed | Status | Evidence or next owner |
| --- | --- | --- | --- | --- |
| Eligible material IDs | Exact inventory set | Record observed IDs or counts with an inventory diff | Pass, fail, manual review, or skipped | Dry-run and batch reports |
| Dependent content | Expected media, author and taxonomy associations | Record exceptions | Pass, fail, manual review, or skipped | Reports and draft inspection |
| Legacy URL | HTTP 301 to the expected destination, then direct HTTP 200 | Record status and location | Pass, fail, manual review, or skipped | Probe results |
| Internal link | Expected href and fragment target | Record old and new href | Pass, fail, manual review, or skipped | HTML and headless response |
| Source-specific layout and SEO | Accepted by an editor | Record unresolved differences | Pass, fail, manual review, or skipped | Editor approval |

A passed fixture run establishes the automated baseline. Record the real-site rehearsal and manual approval separately before scheduling a cutover.
