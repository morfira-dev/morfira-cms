# ADR 0018: Manual redirects retain stable page ownership

- Status: Accepted
- Date: 2026-10-12

## Context

Automatic slug history covers page renames, but migrations and campaign URLs also need editor-managed redirects. Storing an editable source-to-target string pair would permit chains and stale targets.

## Decision

Manual redirects use the same stable page ownership model as automatic slug history. A redirect stores its source slug and page identifier; resolution joins that owner to its current active route and therefore always produces one direct `301` hop.

Creation is allowed only for active pages. Active route slugs and redirect sources share a reserved namespace. Deletion is scoped by both page identifier and source slug so one page cannot release another page's redirect.

## Alternatives and constraints

- Raw source-to-target pairs were rejected because they allow chains and stale targets.
- External URLs and wildcard matching were excluded to keep resolution local, deterministic and safe.
- Creating redirects for inactive pages was rejected because there is no valid public target route.

## Consequences

- Redirects follow later page renames without updates or chains.
- Unpublished pages cannot be redirect targets.
- Reusing a reserved source slug requires explicit removal by its owner.
- Automatic and manual redirect history share the same resolution path and lifecycle cleanup.

## Migration impact

The existing redirect table already stores source slug and stable page ownership, so no schema migration is required. Existing automatic redirect rows remain valid.

## Verification

Application tests cover active-page creation, inactive-page rejection and ownership-scoped removal. OpenAPI generation, frontend request tests, full static analysis and browser inspection verify the public and editorial boundaries.
