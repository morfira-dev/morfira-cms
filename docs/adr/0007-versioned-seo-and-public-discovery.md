# ADR 0007: Version SEO settings with content snapshots

## Status

Accepted.

## Context

Search metadata affects the public meaning of a page and must change atomically with its content. Reading SEO fields from a mutable draft while serving an older published body would produce inconsistent titles, canonical URLs and crawler directives.

## Decision

SEO title, description, `noIndex` and the Open Graph media reference belong to the page revision and are copied into each immutable publication snapshot.

Canonical URLs are generated from the configured public site URL and active published slug. Editors cannot enter arbitrary canonical URLs in the first slice. `robots.txt` points to a generated sitemap. The sitemap reads only active publication snapshots and excludes snapshots marked `noIndex`.

Public HTML emits canonical, description, robots, Open Graph and Twitter metadata. A `noIndex` snapshot also receives an `X-Robots-Tag` header. Draft preview remains `noindex` and `no-store` regardless of the draft setting.

## Consequences

- Publishing is the only operation that changes public SEO output.
- A rollback can reactivate a snapshot together with its matching metadata.
- Sitemap generation never reads mutable drafts.
- Deployments must configure `PUBLIC_SITE_URL` to the externally reachable origin.
- Arbitrary cross-domain canonicals and redirect management remain outside this slice.
