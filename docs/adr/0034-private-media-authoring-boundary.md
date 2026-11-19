# ADR 0034: Keep private media behind the authoring boundary

## Status

Accepted.

## Context

Authenticated WordPress imports can read attachments that must remain protected in Morfira. The existing public media directory serves immutable files without an application session.

## Decision

Keep private variants outside the web root and deliver them through an editor-authorized endpoint with no-store responses. Persist visibility on the asset. Do not publish new snapshots that reference private assets. Existing public assets retain their visibility.

## Consequences

Operators can review private images in authoring and preview. Content authors must replace or remove private references before publishing publicly. Private files and the database must be backed up together.

## Verification

Test authenticated and anonymous delivery, publication rejection, import replay, and rollback on disposable MariaDB.
