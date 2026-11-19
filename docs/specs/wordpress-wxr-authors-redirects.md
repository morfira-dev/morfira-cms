# WordPress WXR authors and redirects

## Status

Implemented

## Problem and goals

Preserve editorial attribution and old public links when migrating a WordPress site. Create author profiles as content, link new post drafts by stable entry ID, and register permanent redirects for imported pages and posts.

## Actors and flow

An operator runs the existing WXR command. Dry-run reports author and redirect outcomes without writes. Apply creates author profiles, attaches post relations and stages supported redirects in the import transaction. The operator publishes the destination through the usual CMS workflow.

## Data and contracts

WordPress author declarations become ordinary entries in the `wordpress-authors` schema. A source key and a fingerprint of the WordPress login identify an author. The post records an optional `wordpressAuthor` relation and a readable `wordpressAuthorName` attribution. Replays preserve existing profile metadata and drafts.

Old locations are accepted only for public WordPress items with an HTTP(S) link on the exported blog host and port. Native URLs take priority. Pending redirects target stable page or entry IDs and resolve directly to the destination's current published path. Publishing exposes the redirect, unpublishing makes it dormant, and renaming the target updates its destination automatically.

## Failure modes and security

Profiles never grant login privileges. The importer ignores author email, password and role metadata. Missing creators, incompatible schemas, duplicate sources and unsupported URLs appear in the report. Redirect destinations stay local. Neither drafts nor unpublished states appear in anonymous delivery. Existing imports lacking recorded destination IDs are not matched by slug.

## Acceptance criteria

- [x] Dry-run shows planned author imports, post links and redirects with zero writes.
- [x] Apply creates public-facing attribution content and links posts by UUID without creating accounts.
- [x] Old URLs return no redirect before publication, return a direct 301 after publication, and stop redirecting on unpublish.
- [x] Repeat imports preserve author identity, ownership and draft content.

## Verification plan

Parser and publishing tests, backend gate, and a production CLI/browser journey against disposable MariaDB.

## Rollout

Run the additive migrations before using the updated importer. Existing manual and automatic page redirects retain their existing lifecycle.
