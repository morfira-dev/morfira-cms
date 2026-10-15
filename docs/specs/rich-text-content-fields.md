# Rich-text content fields

## Status

Implemented

## Problem

Custom content types support plain text and multiline text, but editors cannot author structured formatting for articles and other content entries. Treating arbitrary browser HTML as trusted content would expose public HTML delivery and headless consumers to unsafe markup.

## Goals

- Let content schemas define a `richText` field.
- Reuse the Admin Studio rich-text editor for entry authoring.
- Sanitize rich-text entry values on the server before draft persistence and publication.
- Preserve sanitized HTML in immutable published snapshots and headless responses.
- Render rich-text fields as formatted, sanitized HTML on the complete-CMS surface.

## Non-goals

- Arbitrary HTML, scripts, inline styles, embeds or iframes.
- Per-field sanitizer policies or custom editor toolbars.
- Collaborative editing, remote editor plugins or rich-text relation embeds.
- A separate draft-preview route for entries.

## Actors and permissions

- Owners define rich-text fields in content schemas.
- Authenticated editors author rich-text entry values and publish saved entry versions.
- Anonymous visitors and API clients read only immutable published snapshots.

## User and system flows

1. An owner adds a `richText` field to a content type.
2. Admin Studio renders the shared rich-text editor for that field.
3. Create and update entry use cases sanitize the submitted HTML before schema validation and persistence.
4. Publication sanitizes again as a defense for legacy records and copies the result into an immutable snapshot.
5. The headless API returns the sanitized HTML string unchanged; complete-CMS HTML applies the same sanitizer before trusted rendering.

## Contracts

- `ContentField.type` adds the backward-compatible `richText` enum value.
- `EntryValue` remains a scalar string, so existing request and response shapes do not change.
- The allowed markup and link policy are the same as page RichText blocks.
- Sanitized rich text is limited to 20,000 characters.

## Data and invariants

- Browser-produced HTML is never trusted.
- Sanitization happens before entry validation and persistence.
- Visually empty sanitized content becomes `null`; a required rich-text field therefore fails validation.
- Existing content types and published snapshots remain compatible without migration.
- Published snapshots remain immutable and never read mutable drafts during delivery.

## Failure modes

- Unsupported field definitions and non-string rich-text values return `422`.
- Required visually empty content returns `422`.
- Unsafe elements, attributes and URL schemes are removed rather than persisted or published.
- Sanitizer failure aborts the write or publication and preserves the prior state.

## Security and privacy

- The existing server-owned sanitizer allow-list remains the single HTML trust boundary.
- Complete-CMS templates mark only sanitizer output as safe.
- Headless consumers receive sanitized HTML but must still place it in an appropriate rendering context.

## Acceptance criteria

- [x] A content type can define a `richText` field through the API and Admin Studio.
- [x] Editors can format and save rich-text entry values with the shared editor.
- [x] Unsafe HTML cannot reach draft storage, published snapshots or rendered HTML.
- [x] Allowed formatting survives save, publication, headless delivery and complete-CMS rendering.
- [x] Required visually empty rich text is rejected and optional empty rich text is stored as `null`.
- [x] Backend, frontend, generated-contract, license and production browser gates pass.

## Verification plan

- Domain/application tests cover the new field type, normalization, size limits and unsafe markup.
- Publishing and presentation tests cover immutable sanitized snapshots, headless output and complete-CMS rendering.
- Frontend tests cover field initialization and validation; the production browser journey authors and publishes rich text.
- Run backend and frontend full checks, dependency license verification, production E2E and `git diff --check`.

## Rollout and migration

No database migration is required because field definitions and entry values are JSON. Rollback removes authoring support only after existing `richText` definitions are migrated to `textarea`; published snapshots remain readable scalar data.
