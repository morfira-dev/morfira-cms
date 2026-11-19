# WordPress WXR image bindings

## Status

Implemented

## Problem and goals

Attach imported WordPress images to the original positions in new page and post drafts. Map the WordPress featured image to native Morfira media references and show unresolved references in the CLI report.

## Flow and contracts

The operator supplies a WXR and optional local uploads directory as before. The existing dry-run reports the planned inline and featured links per item without writes. Apply resolves attachments first, then creates drafts and taxonomy assignments in the same database transaction.

- Pages receive ordered native Image blocks. Their featured image sets Open Graph metadata.
- Posts use optional native media fields interleaved with sanitized rich-text fields. Their featured image uses an optional dedicated media field. Existing fields are retained, and missing fields are added only when needed. A post may contain up to 20 resolved inline images, subject to the content schema's field limit.
- Inline images resolve by WXR attachment identifiers or a unique matching attachment URL. A later export with attachment IDs can reuse mappings recorded by the same source even if it omits the attachment items. Unresolved images are counted and surrounding content is preserved.

## Persistence and compatibility

An additive ledger column records each new attachment's asset UUID, permitting reuse on later imports for the same source. Historic ledger claims have no reliable target UUID and remain consumed. Existing drafts and publication snapshots retain their prior behavior. No API shape changes.

## Security and failure modes

Only Media-generated assets can be linked. The importer does not fetch external URLs or add image markup to the rich-text allow-list. An already removed asset, ambiguous URL, or unsupported file is reported as unresolved. Schema conflicts and capacity limits produce an item error rather than dropping text. A failed apply rolls back claims, drafts and asset metadata and compensates generated variants.

## Acceptance criteria

- [x] Dry-run reports matches without writes.
- [x] Pages and posts store ordered inline references and featured media references.
- [x] Missing links are reported, and replay preserves existing drafts and reuses assets for new content.
- [x] A database failure rolls back the database transaction and newly generated variants.

## Verification plan

Parser, conversion and resolver unit tests, full backend gate, and production CLI integration on MariaDB.
