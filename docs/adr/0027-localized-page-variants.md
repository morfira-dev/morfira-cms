# ADR 0027: Model localization as page variants

## Context
Locale-specific URLs must preserve revision and immutable publication semantics without embedding translations inside one mutable document.

## Decision
Each locale is a normal page aggregate linked by a stable translation group. Locale and group are copied through revisions and published snapshots. Route uniqueness is scoped by locale; the default locale remains unprefixed.

## Alternatives and constraints
One aggregate containing every translation was rejected because it couples revision conflicts and publication timing across languages. Runtime fallback is excluded because public reads must remain deterministic.

## Consequences
Translations can be edited and published independently. Alternate links are derived from active routes in the same group.

## Migration impact
Existing pages receive locale `en` and use their page identifier as translation group.

## Verification
Migration, domain uniqueness, publication routing, canonical and alternate-delivery tests.
