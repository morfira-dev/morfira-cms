# ADR 0005: Session authentication for Admin Studio

## Status

Accepted.

## Decision

Admin Studio uses a server-side Symfony session stored in an HttpOnly, SameSite=Lax cookie. All `/api/v1/admin` and `/_preview` requests require at least `ROLE_EDITOR`. The first installation atomically creates one `ROLE_OWNER` account; concurrent setup requests cannot create multiple owners.

Every state-changing setup, authentication and admin request also requires a session-bound `X-CSRF-Token`. Login attempts are rate-limited. Passwords use Symfony's auto password hasher so their stored representation can be upgraded over time.

Public HTML and `/api/v1/content` remain anonymous and never read authoring data.

## Consequences

- browsers get secure cookie sessions without exposing tokens to JavaScript;
- headless authoring clients must maintain a cookie jar and fetch a CSRF token;
- public delivery remains cacheable and independent from editor sessions;
- API token and OAuth authentication can be added later as another firewall without changing domain code.
