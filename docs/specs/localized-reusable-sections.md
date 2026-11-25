# Localized reusable section variants

## Status
Implemented

## Problem
Editors need translated versions of recurring page sections and a clear indication of the language and revision selected for each page.

## Goals
- Group related library sections by translation and version each language variant independently.
- Retain language-neutral library sections for shared blocks and existing pages.
- Show the content language and version in insertion, refresh, and usage surfaces.

## Actors and permissions
Editors use the existing authenticated section and page authoring APIs. Existing edit and CSRF permissions apply.

## User and system flows
1. An editor creates a neutral section, a localized section, or a translation of a section already in the library.
2. The page builder offers a linked insertion for the page locale and for neutral sections. The editor selects a specific variant.
3. A placement keeps its original section identifier. Its individual and bulk refresh actions apply versions from that variant.
4. Content changes create a normal draft revision. Publishing promotes the exact revision to the public HTML and API.

## Contracts
Create accepts optional `locale` and `translationSourceId`. Supplying a source requires a locale. Section and usage responses expose `locale` and `translationGroupId`. Section updates retain the original locale and group.

## Data and invariants
Normalized locales use the same format as page languages. A translation group may contain one variant per locale. Library names remain unique across the catalog. Versions and placement IDs are independent across sibling variants. A neutral section is available on all pages. A localized linked placement must match the authored page locale. Independent block copies have no locale linkage.

## Failure modes
A missing translation source returns 404. Duplicate locale or name conflicts return 409. Invalid locale or incompatible linked drafts return 422. Section deletion leaves the materialized blocks available in existing page revisions.

## Security and privacy
Language and translation metadata belong to the authoring catalog. Public delivery stays based on immutable page snapshots.

## Acceptance criteria
- [x] Old sections remain language-neutral and copy or linked insertion keeps working.
- [x] Editors can create a translated sibling and see the group, locale, and exact version.
- [x] Published localized pages receive the chosen variant and neither single-page nor bulk refresh switches to a sibling automatically.
- [x] Invalid locale and duplicate sibling locale are rejected.

## Verification plan
The [catalog tests](../../back/tests/Content/Application/ReusableSectionTest.php) cover variant creation and independent revisions. The [page API tests](../../back/tests/Content/Presentation/Http/LinkedSectionsControllerTest.php) cover incompatible placements and explicit detachment. The [Chromium journey](../../front/e2e/critical-journey.spec.ts) verifies variant selection and delivery over HTML and the headless API. The MariaDB upgrade rehearsal preserved the blocks and recorded links of the legacy sections.

## Rollout and migration
Add section locale and translation-group metadata. Existing section IDs, blocks, links, and published content remain valid.
