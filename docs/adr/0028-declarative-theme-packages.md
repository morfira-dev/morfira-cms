# ADR 0028: Register declarative trusted theme packages

## Context
Multiple themes require extensibility without turning content values into executable paths.

## Decision
Themes are deployment-installed packages that register immutable manifests and renderer identifiers in the Symfony container. Page snapshots store stable theme/template IDs; a registry resolves them to trusted Twig resources.

## Alternatives and constraints
Uploaded Twig/PHP themes and arbitrary stored template paths are prohibited. A database-only theme editor is deferred.

## Consequences
Theme installation requires deployment, while editor selection stays safe and portable. Headless clients continue receiving layout identifiers.

## Migration impact
The existing Morfira manifest preserves all stored identifiers.

## Verification
Registry validation, catalog authorization and renderer resolution tests.
