# ADR 0039: Localize reusable sections with explicit variants

## Context
Page translations need reusable sections whose language and version can be reviewed independently.

## Decision
Group section variants by a stable translation identifier. Give each variant its own authoring ID and version. A linked placement continues to point to exactly one variant. Studio offers neutral or matching-language variants for linked insertion.

## Alternatives and constraints
Automatic fallback or selecting a translation during rendering would change the meaning of existing links and make published output depend on the catalog. Explicit authoring selection uses the existing revision and publication boundaries.

## Consequences
Section variants can be revised at different times. Editors can use a neutral section when content is intentionally shared across languages.

## Migration impact
The change adds nullable locale and translation-group storage, and legacy sections are given their own groups without altering their linked IDs.

## Verification
Verify legacy neutral sections, locale and group constraints, Studio selection, and publication without runtime catalog lookup.
