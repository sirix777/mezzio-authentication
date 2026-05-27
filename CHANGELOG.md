# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - Unreleased

### Added
- Stable release of the token-based authentication package for Mezzio.
- Documented `sirix.authentication.context`, `sirix.authentication.token`, and `sirix.authentication.actor` as stable request attribute integration points.

### Changed
- Updated routing integration dependency to stable `sirix/mezzio-routing-contracts:^1.0`.
- Removed pre-1.0 Composer stability metadata.
- Clarified that `AuthManagerInterface` is the canonical HTTP request-aware API.
- Clarified that `SecurityActorProviderInterface` is for non-request or application-managed contexts and is not automatically synchronized with the current HTTP request.
- Updated routing attribute documentation for `sirix/mezzio-routing-attributes:^1.0`.

### Documentation
- Removed the pre-1.0 warning.
- Documented request-bound authentication state, RBAC integration through `sirix.authentication.actor`, session middleware ordering, and cookie transport production recommendations.

## [0.1.0] - 2026-05-11

### Added
- Initial release of the authentication package for Mezzio.
- `AuthenticationManager` for login/logout/check/guest operations.
- Token-based authentication with `AuthToken` value object.
- `TokenAuthenticator` for authenticating tokens and resolving actors.
- `AuthenticationContext` for holding current authentication state.
- `TokenStorageProvider` with support for multiple storage backends.
- `NullTokenStorage` for testing/stateless scenarios.
- `SessionTokenStorage` for session-based token persistence via `mezzio/mezzio-session`.
- `BearerTokenTransport` for extracting tokens from `Authorization: Bearer` header.
- `CookieTokenTransport` for extracting/attaching tokens via cookies.
- `PayloadActorProvider` for extracting actor roles from token payload.
- `ContextActorProvider` for resolving the current actor from authentication context.
- `GuestActor` with default `guest` role.
- `TokenActor` for actors with token-derived roles.
- `NullActorProvider` for testing.
- `AuthenticateMiddleware` — PSR-15 middleware requiring authentication (throws 401).
- `OptionalAuthenticateMiddleware` — PSR-15 middleware for optional authentication.
- `GuestOnlyMiddleware` — PSR-15 middleware allowing only unauthenticated users (throws 403).
- `#[Authenticated]` and `#[GuestOnly]` attributes for optional routing-attributes integration.
- `AuthenticationException` (401) and `AlreadyAuthenticatedException` (403).
- `AuthenticationAttributes` enum for request attribute keys.
- Full PSR-11 factory support with `ConfigProvider` for laminas-servicemanager.
