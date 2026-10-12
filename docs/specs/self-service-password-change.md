# Self-service password change

## Status
Implemented

## Problem
Authenticated users cannot rotate their own password without database intervention.

## Goals
- Let every authenticated user change their password after proving the current password.
- Preserve the existing password policy and session authentication model.

## Non-goals
- Forgotten-password email flows, invitations and administrator resets.

## Actors and permissions
- An authenticated user may change only their own password.

## User and system flows
1. The user enters current, new and confirmation passwords in Settings.
2. The backend verifies the current hash and validates the new password.
3. The repository atomically replaces the hash and the current session ends so the user signs in with the new credential.

## Contracts
- `PUT /api/v1/auth/password` accepts `ChangePasswordRequest` and returns `204`.

## Data and invariants
- New passwords contain at least 12 characters and are never returned or logged.

## Failure modes
- Wrong current password returns `422`; unauthenticated requests return `401`; malformed payloads return `422`.

## Security and privacy
- The route requires a session and CSRF token.
- Password values and hashes must not appear in responses or logs.

## Acceptance criteria
- [x] Correct current credentials replace the stored hash.
- [x] Incorrect current credentials leave the hash unchanged.
- [x] Short or mismatched passwords are rejected.
- [x] Admin Studio returns to sign-in after success without retaining password fields.

## Verification plan
- Positive and negative application/HTTP tests, frontend API tests and build.

## Rollout and migration
- No schema migration or session migration is required.
