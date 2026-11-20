# WordPress image captions

## Status

Implemented

## Problem

WordPress Image blocks can carry a `figcaption`. Even when the image is resolved, the Morfira page editor currently receives the caption as a detached text segment. Editing or reordering the image does not carry the caption with it.

## Goals and scope

- Map a short plain-text caption on a single-image figure to the native page image block's caption.
- Apply the same rule to WXR and REST HTML in dry-run and apply.
- Keep an unresolved image's caption and captions containing rich content in the existing sanitized text flow. Preserve post field layout and caption formatting.

## Actors and flow

The WordPress import operator previews the draft import or applies it through the existing command. An editor can then move the page image and caption together. Page previews, published HTML and the headless API use the existing image block contract.

## Contracts and data

- A figure with one direct image, or one image directly wrapped in a link, and one following plain-text caption can map to an image block with `data.caption` when the attachment resolves. Whitespace-only and over-500-character captions stay in the normal text path.
- The import still resolves attachments from source metadata and the existing ledger. No new fetches, report fields, or persistence changes are required.
- Existing drafts and immutable snapshots are preserved on replay.

## Safety and failure handling

Native captions contain plain text and are escaped by the delivery templates. Figures with mixed or ambiguous content go through the existing sanitizer. A failed attachment match still increments the missing-image count and preserves surrounding text.

## Acceptance criteria and verification

- [x] Resolved WXR and REST page images with an eligible caption produce one native image block with the caption in its data. Publication exposes that caption via HTML and the headless API.
- [x] Formatted, unresolved, and oversized captions remain in the sanitized text flow. Posts keep their existing layout.
- [x] Dry-run creates no records, and reapplying the import does not alter existing drafts.

Run the segment tests, the full backend gate, and the production WordPress CLI integration exercise.
