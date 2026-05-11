# Mezzio Authentication

Token-based authentication package for Mezzio framework with optional attribute support.

> **Pre-1.0 package:** Not yet production-ready. Public API and configuration may change with breaking changes before `1.0.0`.

## Installation

```bash
composer require sirix/mezzio-authentication
```

Package is auto-registered via `extra.laminas.config-provider`.

## Quick Start

### 1. Configuration

Add to `config/autoload/authentication.global.php`:

```php
return [
    'authentication' => [
        'default_storage' => 'session',
        'transport' => [
            'driver' => 'bearer',
            'storage' => 'session',
        ],
        'session' => [
            'prefix' => '_authentication.tokens.',
        ],
        'storages' => [
            // optional named storage mapping: <name> => <container service id>
            // 'redis' => App\Authentication\Storage\RedisTokenStorage::class,
        ],
        'cookie' => [
            'name' => 'mezzio_authentication',
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'Lax',
        ],
        'actor' => [
            'roles_key' => 'roles',
            'role_key' => 'role',
        ],
    ],
];
```

### 2. Session Setup (for SessionTokenStorage)

```bash
composer require mezzio/mezzio-session
```

Register `Mezzio\Session\SessionMiddleware` in your pipeline **before** authentication middleware.

Also configure a session persistence adapter for your application (for example cookie-based or cache-backed persistence), per `mezzio/mezzio-session` documentation.

If `mezzio/mezzio-session` is not installed, `SessionTokenStorage` is not wired and the package uses `NullTokenStorage` as fallback.

If a token id is provided by transport but current storage backend is unavailable for that request (for example missing session in request), authentication middleware treats request as guest instead of failing with a storage runtime exception.

### 3. Protect Routes

**Manual middleware registration:**

```php
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;

$app->get('/api/me', [
    AuthenticateMiddleware::class,
    ProfileHandler::class,
], 'profile');
```

**With `sirix/mezzio-routing-attributes` (optional):**

```bash
composer require sirix/mezzio-routing-attributes
```

```php
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/me', name: 'profile')]
#[Authenticated]
final class ProfileHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // User is authenticated
    }
}
```

## Core Concepts

### AuthManager

Main entry point for authentication operations:

```php
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

$manager->login(['userId' => 1, 'roles' => ['admin']]);
$manager->check($request);  // true/false based on request auth context
$manager->guest($request);  // true/false based on request auth context
$manager->actor($request);  // ActorInterface from request auth context
$response = $manager->logout($request, $response); // detaches token from transport
```

### Token Storage

Two built-in storage backends:

- `NullTokenStorage` — tokens are generated but not persisted (testing/stateless).
- `SessionTokenStorage` — tokens stored in session via `mezzio/mezzio-session`.

When `mezzio/mezzio-session` is unavailable, only `NullTokenStorage` is active.

Custom storage implements `TokenStorageInterface`.

### Token Transport

Extracts token ID from requests:

- `BearerTokenTransport` — `Authorization: Bearer <token>` header.
- `CookieTokenTransport` — cookie-based transport.

Custom transport implements `TokenTransportInterface`.

### Actors

Actors represent the authenticated user:

```php
use Sirix\Mezzio\Authentication\Contract\ActorInterface;

$actor->getRoles(); // ['admin', 'editor']
```

- `PayloadActorProvider` — extracts roles from token payload.
- `ContextActorProvider` — resolves actor from authentication context.
- `GuestActor` — default guest actor with role `guest`.

### Middleware

| Middleware | Behavior |
|-----------|----------|
| `AuthenticateMiddleware` | Requires authentication, throws `AuthenticationException` (401) |
| `OptionalAuthenticateMiddleware` | Attempts authentication, passes through regardless |
| `GuestOnlyMiddleware` | Allows only guests, throws `AlreadyAuthenticatedException` (403) |

### Attributes

| Attribute | Middleware Added |
|-----------|-----------------|
| `#[Authenticated]` | `AuthenticateMiddleware` |
| `#[GuestOnly]` | `OptionalAuthenticateMiddleware` + `GuestOnlyMiddleware` |

When `sirix/mezzio-routing-attributes` is installed, attributes auto-inject middleware. Without it, middleware must be registered manually.

## Request Attributes

After authentication middleware processes a request, these attributes are available:

```php
use Sirix\Mezzio\Authentication\AuthenticationAttributes;

$context = $request->getAttribute(AuthenticationAttributes::Context->value);
$token   = $request->getAttribute(AuthenticationAttributes::Token->value);
$actor   = $request->getAttribute(AuthenticationAttributes::Actor->value);
```

`SessionTokenStorage` reads session from request attributes in this order:

1. `Mezzio\Session\SessionInterface::class`
2. `'session'`

## Extensibility

### Custom Actor Provider

```php
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

final readonly class MyActorProvider implements AuthActorProviderInterface
{
    public function getActor(TokenInterface $token): ?ActorInterface
    {
        // Custom logic to resolve actor from token
    }
}
```

Register in your dependencies:

```php
'dependencies' => [
    'factories' => [
        AuthActorProviderInterface::class => MyActorProviderFactory::class,
    ],
],
```

### Custom Token Storage

```php
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;

final readonly class RedisTokenStorage implements TokenStorageInterface
{
    // implement create(), load(), delete()
}
```

### Custom Transport

```php
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;

final readonly class QueryParamTransport implements TokenTransportInterface
{
    // implement fetch(), attach(), detach()
}
```

## Exceptions

| Exception | HTTP Status |
|-----------|-------------|
| `AuthenticationException` | 401 Unauthorized |
| `AlreadyAuthenticatedException` | 403 Forbidden |

Both provide `getStatusCode()`, `getHeaders()`, and `getPublicMessage()` for integration with error handling middleware.

## Design Notes

The package depends on contracts, not on concrete persistence. Built-in implementations cover common use cases, but everything is replaceable via PSR-11 service configuration.
