# ADR 0030: Execute only trusted container-registered extensions

## Context
Extensions need stable contracts, but evaluating uploaded PHP would make the CMS process an unbounded remote-code-execution surface.

## Decision
Extensions are installed by operators and registered as container services. Versioned manifests declare allow-listed capabilities. Typed synchronous hooks expose immutable context and bounded diagnostics.

## Alternatives and constraints
Admin-uploaded code, remote package installation and generic eval hooks are prohibited. Out-of-process workers may be added later through a separate ADR.

## Consequences
Community and commercial packages can extend Morfira through explicit contracts while installation remains a deployment responsibility.

## Migration impact
None; the first registry is in-memory and container-backed.

## Verification
Manifest, compatibility, authorization, hook isolation and architecture tests.
