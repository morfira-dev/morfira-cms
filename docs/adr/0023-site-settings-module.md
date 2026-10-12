# ADR 0023: Own installation settings in a dedicated module

- Status: Accepted
- Date: 2026-10-12

## Context

Site identity and regional defaults do not belong to content drafts, identity accounts or process health.

## Decision

Create a `Settings` module with a versioned singleton repository and owner-only mutation contract. The first slice covers administrative identity and regional defaults only. Public delivery integration will require an explicit immutable publication contract later.

## Alternatives and constraints

- Environment variables remain authoritative for secrets and deployment URLs.
- A generic key-value table was rejected because it removes type validation and ownership.
- Reading mutable settings from public delivery was rejected because public surfaces read immutable snapshots.

## Consequences

- Settings gain typed contracts and optimistic concurrency.
- Additional setting groups require explicit model changes rather than arbitrary keys.
- Complete CMS and headless public settings remain deliberately unchanged in this slice.

## Migration impact

An additive migration creates and seeds the singleton row.

## Verification

Domain/application tests, migration execution, HTTP authorization tests and generated frontend types cover the boundary.
