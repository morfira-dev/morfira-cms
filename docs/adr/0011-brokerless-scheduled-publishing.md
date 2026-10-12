# ADR 0011: Brokerless scheduled publishing

## Status

Accepted.

## Decision

Scheduled publication is represented by a durable record owned by the Publishing
module. A record contains an opaque page or entry identifier, the exact source
version, a UTC due time and its execution status. Only one current schedule is kept
per target.

The Publishing module executes due records through the existing `PublishPage` and
`PublishEntry` application services. It does not copy snapshot construction rules.
The worker is an idempotent Symfony console command intended for cron or a platform
scheduler. A queue or broker is not required.

A changed draft is not automatically substituted for the scheduled version. The
existing optimistic-lock failure is recorded on the schedule, and an editor must
explicitly schedule the new version.

## Consequences

- installations retain a small operational footprint;
- page and entry schedules share one lifecycle and persistence contract;
- immutable public delivery remains independent from scheduling state;
- processing latency is bounded by the external worker interval;
- large installations may later add claiming or queue adapters behind the same
  application boundary without changing the HTTP contract;
- recurring publication and scheduled unpublishing remain separate future slices.
