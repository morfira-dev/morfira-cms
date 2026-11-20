# WordPress internal links

## Status

Implemented

## Problem and goals

Imported WXR and REST drafts can contain absolute links that send readers back to WordPress. Rewrite known links in newly imported drafts to destinations in the current or earlier batches of the same WordPress source.

## Flow and contracts

1. Validate the batch using the existing draft, schema, slug, and replay rules.
2. Build a link index from unambiguous public source permalinks whose items have valid new draft plans or recorded targets from earlier batches. For a recorded target, resolve its current draft route by identity.
3. Rewrite matching anchors in sanitized page and post rich text before persisting. Existing import actions and redirects use the same transaction.

The index accepts absolute HTTP and HTTPS URLs on the configured WordPress hostname and port. Path matching is case-sensitive and tolerates one trailing slash. For query permalinks, only exact root `p` and `page_id` queries are supported. An anchor fragment is preserved. External, relative, unmatched, ambiguous, protected, and rejected destinations keep their original href. A historical target must still exist in the default page locale or the WordPress post content type. No links are fetched. The CLI report and HTTP schemas are unchanged.

## Compatibility and failure handling

Dry-run validates without writing. Replaying an import keeps existing drafts and snapshots intact. A new link can be used publicly once its target is published at that route. Native links store the route chosen at import time and follow the same lifecycle as manually authored local links. Links in an earlier draft to a destination imported later keep the href written in that earlier draft. Claims created before the permalink migration remain valid. Their targets are unavailable to cross-batch rewriting because those claims have no recorded draft identity. See [ADR 0035](../adr/0035-wordpress-permalink-targets.md).

## Acceptance criteria and verification

- [x] WXR and REST links between new public-source items become local in HTML and the headless API, regardless of source item order.
- [x] Legacy query permalinks and fragments are preserved in resolution. Unsupported or unsafe destinations keep their original safe HTML behavior.
- [x] Dry-run and replay retain their existing write guarantees.
- [x] A new draft links to a known earlier batch under the same source key. The earlier draft and its forward links retain their prior revision.
- [x] Historical matching respects source isolation, ambiguity and the current availability of the target.

Run focused parser, ledger, and rewrite tests, the full Docker backend gate, the production CLI journey for both input modes, and the Chromium Studio journey through two imports and publication.
