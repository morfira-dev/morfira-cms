# Specification-driven development

Morfira uses specification-driven development for changes whose behavior, contract, security, data, or architecture can be misunderstood. The specification is the agreement; code, tests, OpenAPI, and documentation are implementations of that agreement.

## Choose the specification level

Use a full written specification for:

- new user-visible capabilities;
- public API or OpenAPI changes;
- database schema or migration behavior;
- authentication, authorization, privacy, or security changes;
- publishing, caching, routing, revision, or snapshot semantics;
- module boundary, extension contract, or infrastructure decisions;
- breaking changes and migrations.

Use a lightweight in-task specification for a narrow regression, internal refactor, dependency update, or documentation correction when observable behavior is already clear. Even lightweight work must state the expected outcome and verification evidence before editing.

## Specification location and naming

Store full specifications in:

```text
docs/specs/<short-kebab-case-name>.md
```

Use one specification per coherent capability. Keep it after implementation as durable product and engineering context. If the decision is architectural, link a corresponding ADR under `docs/adr/`.

## Required specification structure

```markdown
# <Capability name>

## Status
Proposed | Accepted | Implemented | Superseded

## Problem
What user or system problem exists, and why it matters.

## Goals
- Observable outcome.

## Non-goals
- Explicitly deferred or excluded behavior.

## Actors and permissions
- Actor, capability, and denial behavior.

## User and system flows
1. Trigger.
2. State transition.
3. Observable result.

## Contracts
- HTTP/OpenAPI shapes, events, extension contracts, and compatibility rules.

## Data and invariants
- Ownership, lifecycle, uniqueness, versioning, transactions, and retention.

## Failure modes
- Validation, authorization, conflict, retry, rollback, and partial-failure behavior.

## Security and privacy
- Trust boundaries, sensitive data, abuse cases, and mitigations.

## Acceptance criteria
- [ ] Given/when/then outcome that can be tested.

## Verification plan
- Automated tests, static analysis, contract generation, build, and required manual evidence.

## Rollout and migration
- Compatibility, migration, rollback, observability, and release impact.
```

Remove sections only when they are genuinely irrelevant; do not replace them with assumptions hidden in code.

## Development sequence

1. **Discover:** inspect current behavior, contracts, ADRs, data ownership, and tests.
2. **Specify:** write goals, non-goals, flows, invariants, failure modes, and testable acceptance criteria.
3. **Review the boundary:** identify the owning module and affected complete-CMS/headless surfaces.
4. **Design the contract:** update OpenAPI, application ports, events, or migrations before implementation details harden.
5. **Prove the gap:** add a focused failing test or a characterization test where practical.
6. **Implement vertically:** complete one end-to-end behavior without speculative layers.
7. **Verify:** run focused checks, then all affected full gates from `AGENTS.md`.
8. **Reconcile:** update the specification status and make code, tests, OpenAPI, generated types, docs, and ADRs agree.
9. **Release:** choose the Conventional Commit type from actual compatibility impact, not the amount of code changed.

## Compatibility and breaking changes

A change is breaking when an existing supported caller, extension, stored record, URL, template, workflow, or deployment can no longer behave correctly without coordinated migration.

For a breaking change:

- state the break explicitly in the specification;
- document migration and rollback;
- version public contracts where required;
- use `break:`, `type!:`, or a `BREAKING CHANGE:` footer in the commit;
- verify old data and supported clients receive intentional behavior rather than accidental failure.

## Traceability

Acceptance criteria should map to evidence:

| Criterion type | Minimum evidence |
|---|---|
| Domain invariant | Unit test |
| Application flow | Application/integration test |
| HTTP contract | Presentation test plus OpenAPI update |
| Frontend behavior | Component/unit test and build; browser evidence when interaction matters |
| Migration | Migration execution and compatibility check |
| Security rule | Positive and negative authorization/input tests |
| Architecture boundary | Architecture test and ADR when durable |

## Completion rule

Do not mark a specification Implemented while any acceptance criterion lacks evidence, a documented deferral, or an explicit follow-up owner. A passing test suite does not override a mismatch between the implemented behavior and the accepted specification.
