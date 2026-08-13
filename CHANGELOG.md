# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-08-13

### Changed
- **Breaking:** `AuthManagerInterface::login()` is now request- and response-aware. Replace `login($payload, $storage, $expiresAt)` with `login($request, $response, $payload, $expiresAt)`; it creates a token in the configured transport storage and attaches it to the response.
- **Breaking:** authentication requires both a token and actor. An actor provider that returns `null` rejects the token.
- **Breaking:** authentication middleware now propagates `StorageException` when a supplied token cannot be verified because storage is unavailable. `OptionalAuthenticateMiddleware` no longer passes through this failure, preventing `GuestOnlyMiddleware` from failing open.
- `authentication.transport.storage` is validated by `TokenStorageProviderFactory`; an unknown storage now raises `InvalidConfigValueException` during service creation.
- Invalid and expired session token records are discarded.
- Unified the built-in cookie transport default name as `sirix_authentication`.
- Unified the built-in cookie transport `secure` default as `true`;
- Updated the supported `sirix/container-resolver` constraint to `^0.2 || ^1.0`.

### Added
- Added `authentication.bearer.header` and `authentication.bearer.scheme` settings for the built-in bearer transport.

### Migration
- Update login handlers to pass the current request and response to `AuthManagerInterface::login()` and use its returned response.
- Ensure every accepted token can resolve to an `ActorInterface`; return `null` only to reject a token.
- Ensure your error middleware handles `StorageException` for requests carrying a token.

## [1.0.1] - 2026-08-12

### Changed
- Updated `sirix/container-resolver` dependency to `^0.2` (from `^0.1.0`).

## [1.0.0] - 2026-06-02

### Added
- Stable release of the token-based authentication package for Mezzio.
- Documented `sirix.authentication.context`, `sirix.authentication.token`, and `sirix.authentication.actor` as stable request attribute integration points.

### Changed
- Updated routing integration dependency to stable `sirix/mezzio-routing-contracts:^1.0`.
- Removed pre-1.0 Composer stability metadata.
- Clarified that `AuthManagerInterface` is the canonical HTTP request-aware API.
- Clarified that `SecurityActorProviderInterface` is for non-request or application-managed contexts and is not automatically synchronized with the current HTTP request.
- Updated routing attribute documentation for `sirix/mezzio-routing-attributes:^1.0`.
- Moved exception classes to the `Sirix\Mezzio\Authentication\Exception` namespace.
- Made factories use `sirix/container-resolver` for strict container service and configuration resolution instead of silently falling back.

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
