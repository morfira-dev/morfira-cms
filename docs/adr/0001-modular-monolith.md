# ADR 0001: Start as a modular monolith

- Status: accepted
- Date: 2026-10-07

## Context

The CMS must support small websites without distributed infrastructure while retaining clear boundaries for future growth.

## Decision

The first production architecture is a modular monolith. Business capabilities are separate modules with explicit public contracts. Modules run in one process and may share a deployment artifact, but they do not share internal services or access each other's persistence directly.

## Consequences

- Local installation and operations remain simple.
- Transactions can span the parts of one module without distributed coordination.
- Boundaries require automated enforcement.
- A module may be extracted only after operational evidence justifies it.
- Microservices are not an initial extensibility mechanism.
