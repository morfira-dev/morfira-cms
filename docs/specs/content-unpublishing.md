# Content unpublishing

## Goal

Editors can remove a page or structured entry from public delivery without deleting its draft history or immutable publication snapshots.

## Contract

- `DELETE /api/v1/admin/pages/{id}/publish` unpublishes a page.
- `DELETE /api/v1/admin/entries/{id}/publish` unpublishes an entry.
- Both operations are idempotent and return `204` for an existing draft.
- A missing draft returns `404`; an invalid identifier returns `422`.
- Unpublishing removes the active public route.
- Page redirect history is removed with the page route so redirects never resolve to unpublished content.
- A pending publication schedule is cancelled to prevent accidental republishing.
- Drafts, revisions and immutable publication snapshots are retained.

## Acceptance criteria

1. Public reads return `404` after unpublishing.
2. Repeating the request succeeds without creating state.
3. Historical snapshots remain available to the publishing subsystem for audit and deterministic republishing.
4. Admin UI exposes the operation for saved pages and entries.
