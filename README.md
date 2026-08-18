# Mezzio Authentication

[![Latest Stable Version](http://poser.pugx.org/sirix/mezzio-authentication/v)](https://packagist.org/packages/sirix/mezzio-authentication) [![Total Downloads](http://poser.pugx.org/sirix/mezzio-authentication/downloads)](https://packagist.org/packages/sirix/mezzio-authentication) [![Latest Unstable Version](http://poser.pugx.org/sirix/mezzio-authentication/v/unstable)](https://packagist.org/packages/sirix/mezzio-authentication) [![License](http://poser.pugx.org/sirix/mezzio-authentication/license)](https://packagist.org/packages/sirix/mezzio-authentication) [![PHP Version Require](http://poser.pugx.org/sirix/mezzio-authentication/require/php)](https://packagist.org/packages/sirix/mezzio-authentication)

Token-based authentication package for Mezzio framework with optional attribute support.

## Stability

The `2.x` line treats the public contracts, middleware behavior, and request attribute names documented below as stable integration points. See the 2.0 migration notes in the changelog before upgrading from 1.x.

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
        'default_profile' => 'web',
        'profiles' => [
            'web' => [
                'transport' => 'cookie',
                'storage' => 'session',
                'transport_options' => [
                    'name' => 'web_authentication',
                ],
            ],
            'api' => [
                'transport' => 'bearer',
                'storage' => 'redis',
                'transport_options' => [
                    'header' => 'X-Api-Authorization',
                ],
            ],
        ],
        'bearer' => [
            'header' => 'Authorization',
            'scheme' => 'Bearer',
        ],
        'session' => [
            'prefix' => '_authentication.tokens.',
        ],
        'storages' => [
            'redis' => App\Authentication\Storage\RedisTokenStorage::class,
        ],
        'transports' => [
            // optional named custom transport: <name> => <container service id>
            // 'partner_header' => App\Authentication\Transport\PartnerHeaderTransport::class,
        ],
        'cookie' => [
            'name' => 'sirix_authentication',
            'path' => '/',
            'domain' => null,
            // Production cookie profiles must require HTTPS.
            'secure' => true,
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

Each entry in `profiles` pairs one transport with one registered storage. `default_profile` is the explicit profile used by the existing unqualified services (`AuthManagerInterface`, `AuthenticateMiddleware`, and `OptionalAuthenticateMiddleware`). It must name an entry in `profiles`; it is never inferred from configuration order.

Register every application-provided storage and transport as a container service. For example, a Redis storage implements `TokenStorageInterface` and is registered under the `redis` storage mapping above. A custom transport implements `TokenTransportInterface`, is registered under `authentication.transports`, and is selected by that mapping name. Do not carry authentication tokens in URL query parameters: URLs can be exposed through logs, browser history, referrers, caches, and monitoring. Prefer a purpose-specific HTTPS header transport instead:

```php
'profiles' => [
    'partner' => [
        'transport' => 'partner_header',
        'storage' => 'redis',
    ],
],
'transports' => [
    'partner_header' => App\Authentication\Transport\PartnerHeaderTransport::class,
],
```

The mapped service IDs must also be registered by the application, typically in its dependency configuration:

```php
'dependencies' => [
    'factories' => [
        App\Authentication\Storage\RedisTokenStorage::class => App\Authentication\Storage\RedisTokenStorageFactory::class,
        App\Authentication\Transport\PartnerHeaderTransport::class => App\Authentication\Transport\PartnerHeaderTransportFactory::class,
    ],
],
```

`bearer` and `cookie` are reserved built-in transport names and cannot be replaced through `authentication.transports`. `transport_options` is supported only for these built-ins. For each profile, its options override the corresponding global `authentication.bearer` or `authentication.cookie` settings; omitted options inherit the global value. Options are isolated between profiles, so two cookie profiles can use different names and two bearer profiles can use different headers. Custom transport construction remains the application's responsibility and therefore rejects `transport_options`.

### 2. Session Setup (for SessionTokenStorage)

```bash
composer require mezzio/mezzio-session
```

Register `Mezzio\Session\SessionMiddleware` in your pipeline **before** authentication middleware.

Also configure a session persistence adapter for your application (for example cookie-based or cache-backed persistence), per `mezzio/mezzio-session` documentation.

If `mezzio/mezzio-session` is not installed, `SessionTokenStorage` is not wired. `NullTokenStorage` is the fallback only when no configuration explicitly selects `session`; a named or legacy configuration that selects `session` fails container construction until session support is installed.

If a token id is provided by transport but its storage backend is unavailable for that request (for example a missing session attribute), authentication middleware stops the request with `StorageException`. It never treats an unverifiable request as a guest request; this is especially important for `#[GuestOnly]` routes.

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
composer require "sirix/mezzio-routing-attributes"
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

### 4. Use a Named Profile

For a route that must use a non-default profile, obtain it from `AuthenticationProfileProviderInterface` and register the supplied middleware instance. This stays PSR-11-compatible and does not require dynamic service IDs.

```php
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;

/** @var AuthenticationProfileProviderInterface $profiles */
$profiles = $container->get(AuthenticationProfileProviderInterface::class);
$api      = $profiles->get('api');

$app->get('/api/me', [
    $api->authenticateMiddleware(),
    ProfileHandler::class,
], 'api.profile');
```

Use the same profile bundle for its lifecycle operations. Calling `login()` or `logout()` through `$api->manager()` uses the API profile's bearer transport and Redis storage; the analogous `$profiles->get('web')->manager()` uses the web profile's cookie transport and session storage.

```php
$api = $profiles->get('api');

$response = $api->manager()->login($request, $response, [
    'userId' => 1,
    'roles' => ['api-user'],
]);

// After the API authentication middleware established the request context:
$response = $api->manager()->logout($request, $response);
```

`#[Authenticated]` and `#[GuestOnly]` continue to select only the default profile. Use manual route registration for a named profile in this first version.

## Core Concepts

### AuthManager

Main HTTP-facing entry point for authentication operations. Current request authentication state is read from the provided `ServerRequestInterface`; it is not stored in a mutable singleton service. `login()` needs both the request and response: it creates the token using the transport's configured storage and attaches its identifier to the response.

```php
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

$response = $manager->login(
    $request,
    $response,
    ['userId' => 1, 'roles' => ['admin']],
);
$manager->context($request); // AuthContextInterface from request attributes
$manager->check($request);  // true/false based on request auth context
$manager->guest($request);  // true/false based on request auth context
$manager->actor($request);  // ActorInterface from request auth context
$manager->token($request);  // TokenInterface from request auth context
$response = $manager->logout($request, $response); // detaches token from transport
```

For profile-aware applications, resolve the manager from the selected `AuthenticationProfileInterface`; do not mix a context established by one profile with another profile's manager. Logout checks the token storage before deleting or detaching, and fails closed on a mismatch.

For HTTP handlers and middleware, prefer `AuthManagerInterface::actor($request)` or the documented request attributes. Do not resolve a "current user" singleton from the container for per-request authorization.

### Token Storage

Two built-in storage backends:

- `NullTokenStorage` — tokens are generated but not persisted (useful for testing only; issued tokens cannot be authenticated later).
- `SessionTokenStorage` — tokens stored in session via `mezzio/mezzio-session`.

When `mezzio/mezzio-session` is unavailable, `NullTokenStorage` is available only as the default when no configuration explicitly selects `session`; an explicit `session` storage selection fails container construction.

Custom storage implements `TokenStorageInterface`.

Each profile uses its configured storage to issue, read, and delete transported tokens. The token-storage provider factory validates every configured profile storage during container construction. Do not issue tokens through an unrelated storage: token ids do not contain a storage discriminator.

### Token Transport

Extracts token ID from requests:

- `BearerTokenTransport` — `Authorization: Bearer <token>` header.
- `CookieTokenTransport` — cookie-based transport.

Custom transport implements `TokenTransportInterface`.

The package never probes multiple transports or storages to find a token. A cookie credential is processed only by a cookie profile, and a bearer credential only by a bearer profile. Select the profile explicitly when registering the route.

### Actors

Actors represent the authenticated user:

```php
use Sirix\Mezzio\Authentication\Contract\ActorInterface;

$actor->getRoles(); // ['admin', 'editor']
```

- `PayloadActorProvider` — extracts roles from token payload.
- `ContextActorProvider` — resolves actor from authentication context.
- `GuestActor` — default guest actor with role `guest`.

`SecurityActorProviderInterface` is intended for non-request or application-managed security contexts. Its default `ContextActorProvider` reads from the injected `AuthContextInterface` service and is not automatically synchronized with the current HTTP request. It is not a replacement for `AuthManagerInterface::actor($request)` in HTTP code.

An `AuthActorProviderInterface` must return an actor for every accepted token. Returning `null` rejects the token and produces a guest authentication context; it must not be used to represent an authenticated user without an actor.

An `AuthenticationContext` is either fully authenticated (both token and actor) or empty/guest. It never exposes a token without an actor.

### Middleware

| Middleware | Behavior |
|-----------|----------|
| `AuthenticateMiddleware` | Requires authentication, throws `Exception\AuthenticationException` (401) |
| `OptionalAuthenticateMiddleware` | Passes through when no token is supplied or it is unknown; propagates storage failures |
| `GuestOnlyMiddleware` | Allows only guests, throws `Exception\AlreadyAuthenticatedException` (403) |

### Attributes

| Attribute | Middleware Added |
|-----------|-----------------|
| `#[Authenticated]` | `AuthenticateMiddleware` |
| `#[GuestOnly]` | `OptionalAuthenticateMiddleware` + `GuestOnlyMiddleware` |

When `sirix/mezzio-routing-attributes` is installed, attributes auto-inject middleware. Without it, middleware must be registered manually.

## Request Attributes

After `AuthenticateMiddleware` or `OptionalAuthenticateMiddleware` processes a request, these stable attributes are available:

```php
use Sirix\Mezzio\Authentication\AuthenticationAttributes;

$context = $request->getAttribute(AuthenticationAttributes::Context->value);
$token   = $request->getAttribute(AuthenticationAttributes::Token->value);
$actor   = $request->getAttribute(AuthenticationAttributes::Actor->value);
```

Stable attribute names:

```php
'sirix.authentication.context'
'sirix.authentication.token'
'sirix.authentication.actor'
```

These attributes are the package's current-request state boundary and are safe for long-running workers because they live on the PSR-7 request instance.

Only one authentication profile may establish this context on a request. The attributes have stable shared names, so applying authentication middleware from multiple profiles to one request would replace the earlier context and is unsupported.

### RBAC Integration

`sirix/mezzio-rbac` can authorize the current request by reading the actor from:

```php
'sirix.authentication.actor'
```

The authentication package does not depend on RBAC. The integration contract is structural: the actor exposes `getRoles(): array`.

`SessionTokenStorage` reads session from request attributes in this order:

1. `Mezzio\Session\SessionInterface::class`
2. `'session'`

When using session storage, `Mezzio\Session\SessionMiddleware` must run before authentication middleware.

Cookie transport options are intentionally passed through without package-level policy validation, so applications retain control over deployment-specific settings. The historical `authentication.cookie.secure` default is `false` for compatibility: set it to `true` for every HTTPS production deployment, enable HSTS, and use `false` only in an explicit local HTTP-development override. Keep `http_only: true`, choose a `same_site` policy appropriate for the application flow, and provide CSRF protection where cookies are sent automatically. `SameSite=None` requires `Secure` in browsers, so configure both together.

When using the container integration, configure the cookie name with `authentication.cookie.name`. The built-in transport and factory both default to `sirix_authentication`.

Never log tokens, `Authorization` headers, cookies, or authentication payloads. Treat them as credentials even in development logs and exception context.

### Legacy Configuration and Default Compatibility

Existing applications can continue to use `authentication.default_storage`, `authentication.transport.driver`, and `authentication.transport.storage` without defining `profiles`. In that case, the package creates a synthetic legacy default profile from those settings, and existing service IDs, direct concrete constructors, request attributes, and routing attributes retain their behavior.

If `default_profile` is configured, it must identify a named profile and becomes the default for those existing unqualified services. Named profiles remain available only through `AuthenticationProfileProviderInterface`; no fallback occurs for an unknown name or malformed profile configuration.

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

final readonly class PartnerHeaderTransport implements TokenTransportInterface
{
    // implement fetch(), attach(), detach()
}
```

## Exceptions

| Exception | Purpose |
|-----------|---------|
| `Exception\AuthenticationException` | 401 Unauthorized response |
| `Exception\AlreadyAuthenticatedException` | 403 Forbidden response |
| `Exception\StorageException` | Token storage failure |
| `Sirix\ContainerResolver\Exception\MissingContainerServiceException` | Required container service is not registered while a factory builds an object |
| `Sirix\ContainerResolver\Exception\InvalidContainerServiceException` | Container service has an unexpected type |
| `Sirix\ContainerResolver\Exception\InvalidConfigValueException` | Factory configuration value has an unexpected type or unsupported value |

HTTP exceptions provide `getStatusCode()`, `getHeaders()`, and `getPublicMessage()` for integration with error handling middleware. Factory configuration errors are reported by `sirix/container-resolver` exceptions.

## Design Notes

The package depends on contracts, not on concrete persistence. Built-in implementations cover common use cases, but everything is replaceable via PSR-11 service configuration.
