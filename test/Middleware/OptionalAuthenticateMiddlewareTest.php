<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\TokenAuthenticator;
use Sirix\Mezzio\Authentication\TokenStorageProvider;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class OptionalAuthenticateMiddlewareTest extends TestCase
{
    private Psr7Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr7Factory();
    }

    #[Test]
    public function passesRequestThroughWhenUnauthenticated(): void
    {
        $provider = $this->createStub(AuthActorProviderInterface::class);
        $middleware = new OptionalAuthenticateMiddleware(
            new TokenAuthenticator($provider),
            new TokenStorageProvider('null', [
                'null' => new NullTokenStorage(),
            ]),
            new BearerTokenTransport(),
            'null',
        );

        $response = $middleware->process(
            $this->factory->createServerRequest('GET', '/'),
            new class($this->factory) implements RequestHandlerInterface {
                public function __construct(private readonly Psr7Factory $factory) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->factory->createResponse(200);
                }
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function setsAuthenticationAttributesOnRequest(): void
    {
        $provider = $this->createStub(AuthActorProviderInterface::class);
        $middleware = new OptionalAuthenticateMiddleware(
            new TokenAuthenticator($provider),
            new TokenStorageProvider('null', [
                'null' => new NullTokenStorage(),
            ]),
            new BearerTokenTransport(),
            'null',
        );

        $handler = new class($this->factory) implements RequestHandlerInterface {
            /**
             * @var array<string, mixed>
             */
            public array $attributes = [];

            public function __construct(private readonly Psr7Factory $factory) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->attributes = $request->getAttributes();

                return $this->factory->createResponse(200);
            }
        };

        $middleware->process(
            $this->factory->createServerRequest('GET', '/'),
            $handler,
        );

        self::assertArrayHasKey(AuthenticationAttributes::Context->value, $handler->attributes);
        self::assertArrayHasKey(AuthenticationAttributes::Token->value, $handler->attributes);
        self::assertArrayHasKey(AuthenticationAttributes::Actor->value, $handler->attributes);
        self::assertInstanceOf(AuthContextInterface::class, $handler->attributes[AuthenticationAttributes::Context->value]);
        self::assertNull($handler->attributes[AuthenticationAttributes::Token->value]);
        self::assertNull($handler->attributes[AuthenticationAttributes::Actor->value]);
    }

    #[Test]
    public function continuesWhenStorageFailsToLoadToken(): void
    {
        $provider = $this->createStub(AuthActorProviderInterface::class);

        $middleware = new OptionalAuthenticateMiddleware(
            new TokenAuthenticator($provider),
            new TokenStorageProvider('session', [
                'session' => new SessionTokenStorage(),
            ]),
            new BearerTokenTransport(),
            'session',
        );

        $response = $middleware->process(
            $this->factory
                ->createServerRequest('GET', '/')
                ->withHeader('Authorization', 'Bearer broken-token'),
            new class($this->factory) implements RequestHandlerInterface {
                public function __construct(private readonly Psr7Factory $factory) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->factory->createResponse(200);
                }
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
