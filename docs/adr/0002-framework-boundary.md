# ADR 0002: Keep framework dependencies outside the domain

- Status: accepted
- Date: 2026-10-07

## Context

Symfony provides mature HTTP, console, dependency-injection and infrastructure components. Letting framework types define the content domain would make the core harder to test, evolve and reuse.

## Decision

Symfony is used in bootstrap, presentation and infrastructure adapters. Domain objects and domain services use plain PHP and depend only on contracts owned by the CMS.

Doctrine ORM is not part of the initial foundation. Persistence requirements will be designed from concrete content and publishing use cases before selecting or building adapters.

## Consequences

- Domain tests do not require the Symfony kernel.
- Controllers and console commands remain thin adapters.
- Mapping between transport, persistence and domain models is explicit.
- Some additional adapter code is accepted in exchange for stable boundaries.
