# ADR 0020: Store a server-sanitized rich HTML subset

- Status: Accepted
- Date: 2026-10-12

## Context

WYSIWYG editors produce HTML, but client-side filtering cannot protect preview, headless clients or public delivery. A structured editor document would be safer to transform but would introduce a new public payload and renderer contract for the current page block.

## Decision

Keep the existing string contract and sanitize HTML in the UpdatePage application boundary using Symfony HTML Sanitizer. Store only the allow-listed result. Preview and public templates reapply the same sanitizer before marking the result as trusted HTML, which protects legacy records that predate the storage invariant.

Admin Studio uses Lexical for authoring. Its MIT license and the sanitizer dependency are recorded in the repository license report.

## Alternatives and constraints

- Client-only sanitization was rejected because clients are untrusted.
- Arbitrary HTML was rejected because it expands XSS and layout attack surface.
- A custom editor was rejected because selection, input method and accessibility behavior are specialized concerns.
- Tiptap was not selected to avoid mixing its open-source and commercial extension surfaces.
- Quill was rejected after its current release was found affected by a published cross-site scripting advisory.

## Consequences

- Existing block payloads remain compatible.
- Sanitizer policy becomes part of the content contract and must evolve additively.
- Raw Twig rendering is safe only for this invariant-bearing field.

## Migration impact

No schema migration is needed. Existing plain text is sanitized as text-compatible content on its next revision.

## Verification

Application and presentation tests include hostile payloads. Frontend build and browser editing verify the authoring surface. License metadata is checked before dependency installation.
