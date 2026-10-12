# User and role management

## Status

Implemented

## Problem

The first owner can authenticate, but cannot create editor accounts or manage access. Sharing the owner account would destroy accountability and increase security risk.

## Goals

- Let owners list, create, change and remove CMS users.
- Support explicit Owner and Editor access levels.
- Prevent self-lockout and removal of the final owner.

## Non-goals

- Email invitations, password reset and external identity providers.
- Custom permissions, teams or content-level grants.
- Audit log and session revocation.

## Actors and permissions

- Owners can manage users and roles.
- Editors cannot read or mutate the user collection.
- Users cannot demote or delete their own active account.

## User and system flows

1. An owner opens the Users workspace.
2. The owner creates an account with email, initial password and role.
3. The new user can sign in through the existing session flow.
4. The owner can change another user's role or remove that account.
5. Persistence atomically refuses a transition that would remove the final owner.

## Contracts

- `GET /api/v1/admin/users` lists safe user summaries without password hashes.
- `POST /api/v1/admin/users` creates a user from email, password and role.
- `PUT /api/v1/admin/users/{id}` changes `role` between `owner` and `editor`.
- `DELETE /api/v1/admin/users/{id}` removes a user.
- Owner role maps to `ROLE_OWNER` plus `ROLE_EDITOR`; Editor maps to `ROLE_EDITOR`.

## Data and invariants

- Email is normalized and globally unique.
- Passwords contain at least 12 characters and are hashed by the configured password hasher.
- Only supported roles are persisted.
- At least one owner always remains.
- The current actor cannot demote or delete itself.

## Failure modes

- Invalid payloads return `422`; missing users return `404`.
- Duplicate email and protected lifecycle transitions return `409`.
- Editors and anonymous callers receive `403` and `401` through Symfony security.

## Security and privacy

- Passwords and hashes never appear in responses, logs or frontend state after submission.
- Every mutation requires session authentication and CSRF protection.
- Owner authorization is enforced before controller execution.

## Acceptance criteria

- [x] Owners can list and create Owner or Editor accounts.
- [x] Created users use the existing authentication-compatible password hash and role contract.
- [x] Owners can change another user's role and remove it.
- [x] Self-demotion, self-deletion and final-owner removal are rejected.
- [x] Editors cannot access user administration APIs or navigation.

## Verification plan

- Application tests cover creation, normalization, role changes and lifecycle guards.
- Presentation/OpenAPI and frontend request tests cover safe payloads and error mapping.
- Security configuration, PHPStan, frontend checks and browser UI are verified.

## Rollout and migration

The existing identity table already stores multiple users and JSON roles, so no schema migration is required. Rollback removes management endpoints while all created accounts continue to authenticate.
