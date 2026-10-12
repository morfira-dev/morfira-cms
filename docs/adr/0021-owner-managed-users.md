# ADR 0021: Owner-managed users with fixed roles

- Status: Accepted
- Date: 2026-10-12

## Context

Morfira needs separate accounts before advanced workflow or audit features are meaningful. A free-form permission system would be premature and easier to misconfigure.

## Decision

Expose two product roles: Owner and Editor. Owners inherit editorial capability. Owner-only user management is enforced by Symfony access control and repeated in application lifecycle guards for actor-sensitive operations.

Repository mutations lock the relevant user set before demoting or deleting an owner so concurrent requests cannot remove the final owner.

## Alternatives and constraints

- Shared credentials were rejected for security and accountability.
- Arbitrary permission matrices were deferred until concrete workflows require them.
- Email invitations were deferred because outbound mail and token lifecycle are not baseline infrastructure.

## Consequences

- Small installations receive understandable access control without a policy engine.
- Password distribution is manual until invitations and reset flows exist.
- Future permissions can refine Editor capabilities without changing owner inheritance.

## Migration impact

No schema migration is required. The existing first owner already stores both supported Symfony roles.

## Verification

Application tests cover actor and last-owner invariants; OpenAPI, security rules, generated clients and browser behavior cover the external boundary.
