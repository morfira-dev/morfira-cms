# ADR 0019: Use a small History API router for Admin Studio

- Status: Accepted
- Date: 2026-10-12

## Context

Admin Studio needs a small fixed route table, active navigation and browser history. Introducing a general router would add dependency and upgrade surface before nested layouts, loaders or route-level data APIs are needed.

## Decision

Own a typed route table and a focused React hook around `history.pushState`, `replaceState` and `popstate`. Sidebar items render anchors and intercept unmodified same-origin clicks for client navigation.

## Alternatives and constraints

- React Router was not selected because current requirements are a single flat authenticated shell.
- Hash routing was rejected because clean URLs and direct server fallback are part of the deployment contract.
- Route authorization remains server-side; the client only filters navigation and avoids unnecessary requests.

## Consequences

- No new routing dependency or license surface is introduced.
- The implementation remains intentionally small and can be replaced if nested routing becomes material.
- Production hosting must fall back `/admin/*` requests to the Admin Studio entry document.

## Migration impact

The legacy `/` entry normalizes to `/admin/pages`. No persisted data or API contract changes.

## Verification

Route unit tests and browser navigation evidence cover pathname resolution, active state and history behavior.
