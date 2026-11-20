# WordPress internal links

## Status

Implemented

## Problem and goals

Imported WXR and REST drafts can contain absolute links that send readers back to WordPress. Rewrite known links between items in the same import batch to the planned Morfira routes so they work after both drafts are published.

## Flow and contracts

1. Validate the batch using the existing draft, schema, slug, and replay rules.
2. Build a link index from unambiguous public source permalinks whose items have valid new draft plans.
3. Rewrite matching anchors in sanitized page and post rich text before persisting. Existing import actions and redirects use the same transaction.

The index accepts absolute HTTP and HTTPS URLs on the configured WordPress hostname and port. Path matching is case-sensitive and tolerates one trailing slash. For query permalinks, only exact root `p` and `page_id` queries are supported. An anchor fragment is preserved. External, relative, unmatched, ambiguous, protected, previously imported, and rejected destinations keep their original href. No links are fetched. The CLI report and HTTP schemas are unchanged.

## Compatibility and failure handling

Dry-run validates without writing. Replaying an import keeps existing drafts and snapshots intact. A new link can be used publicly once its target is published. Native links store the route chosen at import time and follow the same lifecycle as manually authored local links. Cross-batch reconciliation is outside this slice. Binding order, caption handling, media rollback, and stored author and taxonomy references are preserved.

## Acceptance criteria and verification

- [x] WXR and REST links between new public-source items become local in HTML and the headless API, regardless of source item order.
- [x] Legacy query permalinks and fragments are preserved in resolution. Unsupported or unsafe destinations keep their original safe HTML behavior.
- [x] Dry-run and replay retain their existing write guarantees.

Run focused parser and rewrite tests, the full Docker backend gate, and the production WordPress CLI integration journey with fixtures for both input modes.
