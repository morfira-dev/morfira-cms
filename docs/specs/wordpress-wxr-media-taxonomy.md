# WordPress WXR media and taxonomy import

## Status

Implemented

## Goals

- Import WXR raster attachments from an operator-provided WordPress uploads directory into the media library.
- Import built-in categories and tags and attach them to posts created in the same run.
- Report media and taxonomy outcomes in both dry-run and apply.

## Non-goals

Remote fetching, documents and video, inline image replacement, featured images, custom taxonomies, and guessing destinations for earlier imports are outside this slice.

## Actors and flow

An operator with shell access supplies a local WXR and optionally `--media-dir` pointing to the local uploads root. Run without `--apply` to inspect the report. On apply, review drafts and their terms before publishing.

## Contracts and invariants

The CLI preserves existing item report fields and adds media and taxonomy sections. Attachments use the existing source key and WordPress ID ledger. The path comes from `_wp_attached_file`, or from the part of the attachment URL after the standard `/wp-content/uploads/` segment when metadata is absent. Media is decoded and re-encoded by the Media module. Categories use `wordpress-categories` and tags use `wordpress-tags`. Existing terms retain their metadata. Item-only terms may provide a name and slug when the export lacks global definitions. Only newly imported posts receive assignments. Earlier imports did not retain destination IDs and replay leaves their editorial state intact.

## Failure modes and privacy

Missing files, unsupported formats, invalid paths, and inconsistent taxonomy data produce report outcomes. No network or remote paths are followed. Relative paths must resolve to regular files within the configured root. Protected or explicitly private attachments and attachments of known private source posts are skipped. Imported variants follow the standard media URL policy. Database writes use the existing import transaction. On a caught apply failure, generated variants owned by the failed import are cleaned up. An abrupt process exit may leave unreferenced generated files for operator review.

## Acceptance criteria

- [x] Dry-run reports valid media, categories, tags and post links with zero database or media writes.
- [x] Apply imports re-encoded attachments with alt text, category hierarchy, tags and post assignments.
- [x] Replay does not duplicate assets or overwrite terms and posts.
- [x] Unsafe paths and unsupported images are rejected and draft import cannot expose unpublished posts.
- [x] A failed apply preserves the existing transaction and cleans up generated variants.

## Verification plan

Parser and module tests, the backend gate, and the disposable production CLI journey against MariaDB.
