# Editorial review of page revisions

## Status
Implemented

## Problem
Authors and owners need to coordinate decisions on an exact page revision as later edits continue.

## Goals
- Submit a saved page revision and see it in an editorial queue.
- Record an approval or a request for changes with the responsible user and timestamp.
- Preserve the reviewed revision and its title, slug, and language when the draft evolves.

## Non-goals
- Mandatory approval policies, publishing historical approved revisions, review previews, and scheduled approval-based publication. Direct publishing and scheduling retain their existing contracts.

## Actors and permissions
Editors and owners can submit and list page reviews. Owners decide pending reviews. Mutating API calls use the existing session and CSRF protections.

## User and system flows
1. An author saves a page revision and submits it for review from Studio.
2. An owner opens the queue and approves the revision or requests changes with a comment.
3. The author can see the decision on the page. To request another review after editing, the author submits a newer saved revision.

## Contracts
`GET /api/v1/admin/page-reviews` lists up to 100 reviews newest first, with optional page and status filters. Studio displays the latest 50 matching reviews. `POST /api/v1/admin/pages/{id}/reviews` submits one revision. `PATCH /api/v1/admin/page-reviews/{id}` records a single decision. Comments can contain up to 1,000 Unicode characters.

## Data and invariants
Each page revision can have one review. Repeated submissions return the existing record. A pending review only transitions once. Review metadata never changes the authored blocks, publication schedule, or live snapshot. Deleting the draft and its revisions also removes their review records.

## Failure modes
Missing revisions or reviews return 404. Invalid requests return 422. A second or concurrent decision returns 409. An editor without the owner role receives 403 for decisions.

## Security and privacy
Actor IDs come from the authenticated session. Review metadata and comments remain in admin delivery. Studio renders comments as text.

## Acceptance criteria
- [x] An editor can submit a saved revision, and subsequent draft edits do not change its review.
- [x] An owner can approve or request changes, and an editor cannot decide a review.
- [x] The authored and published content remain on their existing revisions until an explicit save or publication.

## Verification plan
The [backend tests](../../back/tests/Content/Presentation/Http/PageReviewControllerTest.php) cover idempotency, decisions, validation, and persistence. The [production Chromium journey](../../front/e2e/critical-journey.spec.ts) verifies that an owner can approve an earlier submitted revision while the draft and public HTML and API retain their own revisions.

## Rollout and migration
An additive table stores reviews. Existing pages have no review records until submitted.
