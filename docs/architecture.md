# Architecture

## Shape

The CMS begins as a modular monolith. Modules are organized by business capability rather than by technical class type.

```text
src/<Module>/
  Domain/
  Application/
  Infrastructure/
  Presentation/
```

Dependencies point inward:

```text
Presentation ──> Application ──> Domain
Infrastructure ────────────────> Domain/Application ports
```

The domain must not depend on Symfony, Doctrine, HTTP, Twig, queues or a concrete database. This boundary is enforced by an architecture test.

## Delivery modes

The same application core powers two first-class surfaces:

- a complete CMS with server-rendered public HTML;
- a versioned headless API under `/api/v1`.

Admin Studio consumes the versioned API. OpenAPI 3.1 is the authoritative HTTP contract and generates frontend types. Public templates consume delivery view models rather than API JSON shapes.

Authoring endpoints live under `/api/v1/admin` and may expose drafts to authorized editors. The public headless surface will live under `/api/v1/content` and may read only immutable published snapshots.

Admin Studio authenticates with a server-side session cookie. Mutating setup, authentication and authoring requests require a session-bound CSRF header. `ROLE_EDITOR` protects authoring and preview; the first atomic installation creates a `ROLE_OWNER` that also has editor capabilities.

## Initial modules

- `System`: process health and operational diagnostics.
- `Schema`: content-type and field definitions.
- `Content`: documents, field values and relations.
- `Editorial`: drafts, revisions and editorial collaboration.
- `Publishing`: releases, scheduling and rollback.
- `Delivery`: public read models, routing and rendering.
- `Identity`: users, roles and permissions.
- `Media`: assets and transformations.
- `Extensions`: extension manifests, capabilities and lifecycle.
- `Settings`: typed installation identity and regional defaults.

Implemented vertical slices currently cover `System`, `Schema`, `Content`, `Publishing`, `Identity`, `Media`, `Navigation` and `Settings`. The remaining modules are created when their first concrete capability is implemented.

## Rules

1. No business logic in controllers, console commands or templates.
2. Modules communicate through public application contracts or events.
3. Cross-module database access is forbidden.
4. Framework types do not cross into the domain.
5. Commands modify state; queries return purpose-built read models.
6. CQRS is used only where separate read and write models provide measurable value.
7. Domain events are persisted through an outbox before external delivery.
8. Event sourcing is not a default.
9. Extensions use declared capabilities and stable contracts.
10. A small installation must not require Redis, a broker or a search service.

## Delivery composition

HMVC ideas are retained as hierarchical component composition:

```text
Page -> Region -> Block -> Component
```

Components produce view models. They do not create nested HTTP requests or call other controllers.

Draft edits append immutable revisions and use an integer version for optimistic locking. Preview renders the current draft through Twig with private, no-store caching and must never be indexed. Publishing copies an approved revision into a separate immutable delivery snapshot and atomically activates its public slug. Public HTML and headless delivery never read mutable drafts.

Unpublishing removes the active delivery route and cancels a pending schedule while retaining drafts, revisions and publication snapshots. Page redirect history is removed with its route so redirects cannot resolve to unpublished content.

Permanent draft deletion removes mutable lifecycle records in one transaction while retaining immutable publication snapshots. Entry relations have a normalized integrity index: outgoing relations cascade with their source and incoming relations restrict target deletion.

Scheduled publishing stores the exact draft version and UTC due time inside the
`Publishing` module. An idempotent console worker delegates to the same immediate
publication services, so a small installation needs only cron and never a broker.
