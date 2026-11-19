# ADR 0033: Restrict WordPress import credentials to posts and pages

## Status

Accepted. [ADR 0034](0034-private-media-authoring-boundary.md) extends the credential scope to approved attachment requests.

## Context

Private WordPress content requires an authenticated REST edit context. The existing importer also reads related collections and downloads images from the WordPress host or operator-approved CDN hosts.

## Decision

Authentication is opt-in and credentials come from the CLI process environment. Only the posts and pages metadata collections receive Basic authentication. Related collections and downloads stay anonymous. Restricted source items are created as drafts without legacy redirects. The importer skips private attachments because the current media delivery policy serves image variants publicly.

## Alternatives and consequences

Global credential configuration or forwarding Basic authentication to attachments would broaden access beyond the requested content. Replicating WordPress access policies would require a separate authorization contract. Editors review restricted drafts and control any later publication in Morfira.

## Migration and verification

No database migration is required. Tests cover credential scoping, private draft visibility, anonymous media, failed reads and replay.
