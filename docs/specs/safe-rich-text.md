# Safe rich text blocks

## Status

Implemented

## Problem

The RichText page block stores plain text in a multiline input. Editors need headings, lists, emphasis, links and block quotes, while public rendering must never trust browser-produced HTML.

## Goals

- Provide a modern WYSIWYG editor for page RichText blocks.
- Sanitize HTML server-side before it enters a revision or publication snapshot.
- Render the sanitized subset consistently in preview and public delivery.

## Non-goals

- Arbitrary HTML, scripts, inline styles, embeds or iframes.
- Rich HTML fields for custom content types.
- Collaborative editing or editor plugins.

## Actors and permissions

- Authenticated editors author rich text while editing a page draft.
- Anonymous visitors receive only sanitized HTML from immutable snapshots.

## User and system flows

1. An editor formats content with the RichText toolbar.
2. Admin Studio submits HTML in the existing block `content` field.
3. The UpdatePage application service sanitizes the value with the allowed policy.
4. The sanitized HTML is validated, revisioned and later copied into a publication snapshot.
5. Preview and delivery reapply the same policy as a defense for legacy records, then render the sanitized subset without double escaping.

## Contracts

- The existing `richText.data.content` string becomes sanitized HTML without changing its JSON shape.
- Allowed elements are paragraphs, line breaks, headings 2–4, strong, emphasis, underline, strike, ordered/unordered lists, list items, block quote, code and links.
- Links allow safe HTTP(S), mail and relative targets; unsafe attributes and protocols are removed.

## Data and invariants

- Sanitization happens before domain validation and persistence.
- Empty visual content is rejected after stripping markup.
- Stored content is limited to 20,000 characters after sanitization.
- Existing plain-text blocks remain valid HTML text and render compatibly.

## Failure modes

- Invalid or empty content returns `422`.
- Unsafe elements, attributes and URL schemes are removed rather than published.
- Sanitizer failure aborts the revision and leaves the prior version unchanged.

## Security and privacy

- Browser output is untrusted.
- Public templates mark rich text as safe only after applying the server sanitizer.
- No executable content, event attributes, CSS or remote embeds are allowed.

## Acceptance criteria

- [x] Editors can format headings, emphasis, lists, block quotes and links.
- [x] Allowed formatting survives save, preview and publication.
- [x] Script, event-handler and unsafe-link payloads cannot reach rendered output.
- [x] Plain existing RichText content remains readable.
- [x] Every new dependency has a recorded commercially usable license.

## Verification plan

- Domain/application tests cover allowed formatting, XSS removal and empty content.
- Frontend request/build tests verify the unchanged API shape.
- Preview/public template tests assert sanitized formatting is rendered.
- A dependency license report is committed with the feature documentation.

## Rollout and migration

No database migration is required. Existing plain strings are valid text nodes, and render-time sanitization protects records created before this invariant. Rollback must change templates back to escaped rendering before removing server sanitization.
