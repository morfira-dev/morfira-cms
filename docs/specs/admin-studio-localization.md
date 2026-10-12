# Admin Studio localization

## Status
Implemented

## Problem
Admin Studio exposes its navigation, forms, validation feedback and editorial workflows only in English, while Russian-speaking operators need a complete Russian interface.

## Goals
- Keep English as the default Admin Studio locale and provide a complete Russian translation.
- Centralize user-facing messages behind a typed translation contract.
- Allow the operator to switch between Russian and English without reloading or changing stored content.
- Keep the personal Admin Studio locale independent from the public site's default content locale.
- Localize MUI components, browser document metadata and date formatting consistently.

## Non-goals
- Automatic translation of authored content, public pages or API data.
- Translating stable technical values such as slugs, API names, locale tags, roles, extension capabilities and identifiers.
- Translating or changing authored content when the Admin Studio locale changes.

## Actors and permissions
- Anonymous setup and login users can choose the interface language.
- Authenticated editors and owners use the same locale preference across Admin Studio.

## User and system flows
1. Anonymous setup and login start in English.
2. After authentication, Admin Studio applies the locale stored in the user's profile.
3. The user can switch between Russian and English from the interface; the server persists the preference and the UI updates immediately.
4. Content values and HTTP payloads remain unchanged.
5. An owner changes the public site's default locale separately under site settings; this does not change the Admin Studio locale.

## Contracts
- Translation keys and interpolation parameters are defined in frontend source and statically checked by TypeScript.
- `AuthenticatedUser` includes `adminLocale`.
- `PUT /api/v1/auth/preferences` accepts the current user's `adminLocale`.
- The existing backend `defaultLocale` setting remains the authoritative public-site locale and is not reused as an Admin Studio preference.
- The preference endpoint requires an authenticated session and the existing CSRF token.

## Data and invariants
- Supported locales are `ru` and `en`; new and migrated users default to `en`.
- The preference belongs to the authenticated user and is stored in the identity module.
- Missing translations fall back to the English source message in development-safe fashion.
- Localized labels never replace stable API values.

## Failure modes
- Unknown locale values are rejected with `422`; missing legacy values migrate to English.
- Missing interpolation parameters remain visible rather than altering application state.

## Security and privacy
- The preference contains only a locale identifier and is stored with the user profile.
- Backend error messages are not parsed as trusted markup.

## Acceptance criteria
- [x] Setup, authentication, navigation and every current Admin Studio workspace are usable in Russian.
- [x] A language switch changes Russian and English labels immediately and survives login, reload and device changes.
- [x] Admin Studio locale and public-site default locale can be changed independently.
- [x] MUI locale, document language and formatted dates follow the selected interface locale.
- [x] Frontend tests, types, production build and the critical browser journey pass.

## Verification plan
- Translation contract unit tests, route tests, frontend full check and production Playwright journey in Russian.

## Rollout and migration
- Add an `admin_locale` column with `en` as the default for existing and new users. Rollback removes only the preference and does not affect site content.
