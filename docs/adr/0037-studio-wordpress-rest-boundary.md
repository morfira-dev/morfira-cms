# ADR 0037: Bound public WordPress reads from Admin Studio

## Status

Accepted

## Context

The WordPress CLI reads remote REST collections through an operator-controlled process. A synchronous Studio action runs inside the HTTP service and needs a smaller outbound work budget.

## Decision

Expose an owner-only, CSRF-protected REST import route using the public HTTPS endpoint policy and TLS transport. Limit Studio metadata reads separately from the CLI, and complete the snapshot before applying drafts and recording history. Existing attachment mappings can be reused by source key. Owners can opt into anonymous public image downloads during apply. The REST host is allowed by default and owners may name additional CDN attachment hosts for that request under [ADR 0032](0032-wordpress-rest-media-host-trust.md). A separate batch budget limits the number and duration of transfers. Commit the history and imported records together and compensate generated media files if that transaction fails.

## Alternatives and consequences

The CLI budget supports more collections and larger migrations. It remains available for authenticated WordPress access. A browser action can reach an upstream bound and require operators to continue through the CLI. The design needs no new worker infrastructure or database schema.

## Verification

Check endpoint safety, aggregate budget, owner and CSRF enforcement, and transaction behavior. Exercise both Studio formats and the existing CLI regression suite.
