# WordPress migration in Admin Studio

## Status

Implemented

## Problem and goals

Site owners need to import WordPress pages and posts without shell access. Admin Studio accepts a WXR file, previews a dry-run and applies it as Morfira drafts with a reviewable report.

## Actors and flow

1. A site owner selects a WordPress export and a stable source key for that installation.
2. Studio submits the file in dry-run mode and displays the result.
3. The owner confirms apply. Changing the file or key clears the displayed plan and requires a new preview in Studio.
4. Successful apply reports created and skipped items. Existing drafts are preserved on replay.
5. Owners can search material titles, IDs and routes, filter by type and outcome, and inspect detailed diagnostics before or after apply.
6. Owners can download the complete current report as JSON, including rows outside the active filters.
7. Planned rows are selected by default. Owners can change the selection row by row or across the current filter results and apply a subset. Changing filters preserves the selection.
8. Owners can preview the source again with the same key and continue with materials that remain eligible.

## Contract and limits

`POST /api/v1/admin/imports/wordpress` receives multipart fields `file`, `source` and `mode` (`dry-run` or `apply`). The JSON report exposes `mode`, `input`, `source`, `counts`, `skippedTypes`, `items`, `media`, `taxonomy` and `authors`. The WXR reader and transaction match the CLI. Import is bounded by the existing 8 MiB and 5,000-item limits.

Material rows may include a bounded plain-text source title for ordinary, unprotected content. The report groups item-level media, author and redirect diagnostics with each material. Companion rows detail attachments, terms, assignments and authors. The browser exports the same API report with a source-and-mode filename.

Apply accepts an optional multipart text field `selectedIds` containing 1–5,000 unique, comma-separated WordPress page or post IDs. Each ID is a positive decimal string of at most 20 digits. Omission retains whole-source behavior. Selective apply reports `scope: selection`, selected material outcomes, and related data. Taxonomy planning includes referenced terms and their ancestor declarations. Author planning uses the logins of selected posts. Both operate on materials that remain unclaimed when the transaction begins. Attachment diagnostics still cover the WXR attachment index, whose metadata supports existing image mapping. Selective reports leave the unsupported-type aggregate empty. Links in new drafts can resolve to eligible targets in current and earlier batches through the [internal link policy](wordpress-internal-links.md).

This browser workflow accepts WXR only. REST sources and local uploads directories remain available through CLI. Source files are parsed from PHP's request-scoped upload storage. The importer creates drafts and stages eligible redirects under the existing publishing rules.

## Security and failure handling

Both modes require an owner session and a CSRF token. The operator supplies no paths or network addresses. Malformed input fails validation, and a concurrent import conflict can be retried. Dry-run performs no content writes. Apply uses the existing ledger and transaction. The response contains plan metadata and omits raw content.

Malformed selections, unknown page or post IDs, and selections supplied with dry-run are rejected before writes. The import processes selected materials in WXR order and records their normal ledger entries transactionally.

Report search, filtering and download run in the current browser page. Studio does not retain a report after navigation or a source change. The JSON omits raw WXR, WordPress logins and protected item titles.

## Acceptance criteria and verification

- [x] A site owner can upload WXR, review its report, and explicitly apply it through Studio.
- [x] Anonymous, editor-only, missing-CSRF and malformed requests are rejected.
- [x] Dry-run and replay retain existing guarantees and public routes require publication.
- [x] OpenAPI and generated types agree with the HTTP endpoint, and Docker browser and backend checks cover the workflow.
- [x] Owners can locate material rows by searchable fields and filters and inspect available migration diagnostics.
- [x] The browser can export a complete report in both modes independently of the current filters.
- [x] Owners can import selected planned rows across filters and return for the remaining drafts.
- [x] The API rejects malformed or unknown selections and only imports selected drafts and their associated data.
