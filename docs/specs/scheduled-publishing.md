# Scheduled publishing

## Status

Implemented.

## Goal

Editors can schedule an exact page or entry draft version for future publication.
Morfira keeps the runtime lightweight by exposing an idempotent CLI worker that can
be invoked by cron or a platform scheduler without requiring a message broker.

## Non-goals

- recurring publications;
- scheduled unpublishing;
- automatic publication of a newer draft than the scheduled version;
- a built-in distributed scheduler or queue;
- navigation publication schedules.

## Actors and flows

An authenticated editor reads, creates, replaces or cancels the schedule associated
with one page or entry. The Admin Studio sends an RFC 3339 timestamp derived from the
editor's local date and time. The API stores and returns UTC.

An operator invokes `bin/cms cms:publishing:run-scheduled`. The worker processes due
pending schedules in chronological order, publishes through the existing page or
entry publication service, and records the result.

## Contract

- `GET /api/v1/admin/{pages|entries}/{id}/schedule` returns the latest schedule or
  `404` when none exists.
- `PUT /api/v1/admin/{pages|entries}/{id}/schedule` accepts `sourceVersion` and
  `scheduledAt`, creates or replaces the schedule and returns it.
- `DELETE /api/v1/admin/{pages|entries}/{id}/schedule` cancels a pending schedule.
- `scheduledAt` is an RFC 3339 date-time strictly in the future.
- one schedule exists per target; replacing a completed, failed or cancelled
  schedule reuses its identity and returns it to `pending`.
- schedule status is `pending`, `completed`, `failed` or `cancelled`.

## Invariants

- a schedule identifies exactly one target kind, target UUID and positive source
  version;
- scheduling validates that the draft and exact source version exist now;
- execution publishes only the recorded version;
- publication still creates or activates the existing immutable snapshot;
- changing a draft after scheduling never silently publishes the newer version;
- retrying the worker is safe because publication and completion are idempotent;
- public delivery never reads schedule records.

## Failure modes

- invalid payload or non-future time returns `422`;
- missing draft or schedule returns `404`;
- a stale version at scheduling time returns `409`;
- a stale or otherwise invalid draft at execution time marks the schedule `failed`
  with a bounded diagnostic message;
- cancelling a non-pending schedule returns `409`.

## Security

Schedule administration uses the existing authenticated admin firewall and CSRF
protection. The CLI worker is an operational entry point and does not expose HTTP.

## Acceptance criteria

- page and entry editors can schedule and cancel publication;
- schedules round-trip through the versioned OpenAPI contract;
- the worker publishes due schedules but ignores future or non-pending schedules;
- stale scheduled versions fail rather than publishing newer content;
- published HTML and API payloads remain immutable snapshots;
- backend tests, static analysis, frontend tests/build and OpenAPI type generation
  pass in Docker.

## Migration and operations

The migration creates `publishing_schedules` with a unique target key and a due-work
index. Deployments run the worker periodically, for example once per minute:

```cron
* * * * * cd /app/back && php bin/cms cms:publishing:run-scheduled --limit=100
```
