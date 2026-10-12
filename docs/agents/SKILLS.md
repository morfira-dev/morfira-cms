# Morfira project skills

This document routes agents to the correct repository workflow. It complements any native skills supplied by the active agent platform; it does not replace their safety or tool instructions.

Read this file completely before work and apply every workflow relevant to the task.

## Skill selection

| Task | Primary workflow | Required companion workflow |
|---|---|---|
| New product behavior | Specification-driven feature | Backend, frontend, contract, persistence, or security as applicable |
| Bug fix | Contract-preserving diagnosis | Relevant implementation workflow and regression test |
| API change | OpenAPI contract | Backend and Admin Studio client |
| Backend module change | Backend vertical slice | Persistence, contract, or security when crossed |
| Admin Studio change | Admin Studio | OpenAPI contract when data shapes change |
| Schema or migration change | Persistence | Backend vertical slice and rollback/compatibility review |
| Authentication or authorization | Security | Backend, frontend, and API contract |
| Architecture change | Architecture decision | Written specification and ADR |
| Release/tooling change | Release engineering | Full affected verification |
| Documentation-only change | Documentation | Link and command validation |

## Specification-driven feature

1. Follow `docs/agents/SDD.md` and classify the change as lightweight or full-spec.
2. Define observable acceptance criteria before implementation.
3. Identify the owning module and every affected delivery surface.
4. Implement the smallest complete vertical slice.
5. Verify the criteria through automated tests and contract checks.

## Contract-preserving diagnosis

1. Reproduce or characterize the symptom before editing.
2. Trace the request from presentation through application and domain to persistence or delivery.
3. Identify the shared owner of the broken behavior; avoid local UI or controller workarounds.
4. Add a regression test that fails for the original symptom.
5. Fix the owner while preserving payloads, ordering, routes, caching, authorization, and lifecycle behavior unless the specification explicitly changes them.

## Backend vertical slice

Inspect the module under `back/src/<Module>/` and its tests.

- Put invariants and value semantics in Domain.
- Put use-case orchestration in Application.
- Implement ports in Infrastructure.
- Keep HTTP/CLI translation in Presentation.
- Use explicit typed inputs and purpose-built read models.
- Add unit tests for domain/application behavior and focused presentation tests for HTTP contracts.
- Run the full backend check before handoff.

## OpenAPI contract

1. Update `back/openapi/openapi.yaml` first or together with the implementation.
2. Preserve `/api/v1` compatibility unless the approved specification authorizes a break.
3. Keep explicit discriminator mappings for polymorphic content.
4. Regenerate `front/src/api/schema.d.ts`; never hand-edit it.
5. Update backend presentation tests and frontend API tests.
6. Run frontend generation, type checking, tests, and build.

## Admin Studio

- Keep server state and HTTP calls in feature API modules, not reusable UI primitives.
- Use generated OpenAPI types at the boundary.
- Reuse MUI and the existing theme before adding custom primitives.
- Preserve loading, empty, error, conflict, and authorization states.
- Test pure transformations and request behavior; add browser evidence when interaction or layout is material.
- Run `npm run check` in the frontend container.

## Persistence

- Access persistence through module-owned repository ports.
- Never query another module's tables directly.
- Make migrations additive and deterministic whenever possible.
- Preserve optimistic locking, immutable revisions, idempotent publishing, and route uniqueness.
- Review transactional boundaries and retry behavior explicitly.
- Test both successful persistence and conflict/not-found paths.

## Security

- Identify trust boundaries, protected resources, actors, and failure modes before implementation.
- Preserve session-cookie flags, CSRF checks, role enforcement, rate limits, preview privacy headers, and public cache semantics.
- Reject unexpected fields and unsafe media/input formats.
- Do not log secrets, passwords, session identifiers, CSRF tokens, or private content.
- Add negative tests for anonymous, unauthorized, malformed, replayed, and stale requests where relevant.

## Architecture decision

Create or update an ADR when the change affects module boundaries, delivery modes, persistence strategy, extension contracts, security model, public versioning, or mandatory infrastructure.

An ADR must state context, decision, alternatives or constraints, consequences, migration impact, and verification strategy. Keep implementation aligned with accepted ADRs; do not silently reverse them.

## Release engineering

- Keep release configuration in `.releaserc.json` and CI orchestration in `.github/workflows/release.yml`.
- Use English Conventional Commits.
- Validate commit analysis and generated notes when adding a custom commit type.
- Never publish packages or containers unless the user explicitly requests that channel.
- Treat GitHub tags and Releases as the current product version source of truth.
- Run application gates before semantic-release.

## Documentation

- Write repository documentation in English.
- Keep commands executable and paths current.
- Explain durable constraints and decisions, not transient implementation narration.
- Update README, OpenAPI, specifications, ADRs, and release rules together when their claims overlap.
