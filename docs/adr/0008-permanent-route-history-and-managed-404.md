# ADR 0008: Preserve permanent route history by page identity

## Status

Accepted.

## Context

Changing a published slug must not discard inbound links or search authority. Storing simple `old -> new` pairs creates redirect chains after repeated renames and makes rollback difficult. Reusing an old slug for another page can also silently redirect traffic to unrelated content.

## Decision

Every historical slug is reserved against the stable page identifier. Resolution joins that identifier to the page's current active publication route, so every old URL redirects directly to the latest slug with HTTP `301`.

Publishing a new slug atomically:

1. verifies that the target is not reserved by another page;
2. converts the page's previous active slug into redirect history;
3. removes the target from the same page's history when rolling back;
4. activates the new immutable snapshot.

The admin API exposes redirect history as read-only derived state. Manual redirects and releasing reserved slugs require a future explicit workflow.

A published page whose slug is `404` is used as the CMS-managed not-found presentation. It is rendered with HTTP `404`, `noindex` metadata and no canonical URL. When no such page exists, a built-in safe fallback is rendered.

## Consequences

- Repeated renames never create redirect chains.
- Historical slugs cannot be claimed accidentally by unrelated pages.
- Snapshot rollback also restores the matching route and flips redirect history safely.
- Redirect resolution remains a cheap indexed lookup.
- Editors can design the 404 page using ordinary blocks without introducing a separate content model.
