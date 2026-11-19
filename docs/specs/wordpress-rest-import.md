# WordPress REST import

## Status

Implemented

## Problem and goals

An operator can import public WordPress content directly from its standard REST API. The REST reader uses the same draft import and replay ledger as local WXR imports.

## Actors and flow

An operator runs `cms:import:wordpress --api-url=https://site.example/wp-json/wp/v2 --source=legacy-site`. The complete bounded REST metadata batch is collected before the existing dry-run or apply begins. Add `--apply` to persist eligible drafts, aliases, author profiles, terms and media references.

## Contracts

The local file argument and `--api-url` are mutually exclusive. The command reports the selected input and retains the existing JSON fields. It reads the standard posts, pages, media, categories, tags and users collections through numbered pagination. Published posts and pages become drafts. Each request reads up to 50 records, and an import is limited to 5,000 records and 16 MiB of normalized collection JSON across all collections.

## Security and data invariants

The operator provides a canonical public HTTPS namespace. Requests use bounded time and sizes, validated public addresses, and no redirects. The metadata reader does not follow hypermedia links. Apply handles eligible attachments under the [download policy](wordpress-rest-media-download.md) or uses the optional local uploads directory. Public REST is the default, and restricted items use the opt-in [authenticated REST mode](wordpress-rest-private-import.md). Custom endpoints and query-based REST routing are outside scope.

Stable WordPress content and attachment IDs continue to use `--source` as their namespace across inputs. REST author profiles use stable public numeric IDs. WXR author profiles use logins, so using both formats may create distinct author profiles. Reapplying an import cannot overwrite previous drafts. The shared taxonomy importer matches terms by vocabulary and slug. A changed term slug is treated as a distinct term.

## Failure modes

Invalid responses, excess records, duplicate IDs in a collection, or pagination changes abort the read before any write. HTTP failures are reported without exposing remote response bodies. A new invocation reads the source again and uses the existing idempotency and apply transaction.

## Acceptance criteria

- [x] The REST reader fetches all pages of bounded supported collections and normalizes them to the shared import model.
- [x] Dry-run produces a plan without writing, and apply creates draft records.
- [x] Repeated applies, including changed exports, do not duplicate or replace content.
- [x] Invalid or untrusted URLs and failed or partial REST reads cannot cause an apply.

## Verification

Transport and mapper tests cover allowed inputs and response validation. Fixture-driven pagination tests cover multiple and inconsistent pages. The shared production import journey runs the CLI against disposable MariaDB with a fixture transport and verifies dry-run, apply, and edited-source replay.
