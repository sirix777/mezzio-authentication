<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AlreadyAuthenticatedException;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;

final class GuestOnlyMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function passesWhenUserIsGuest(): void
    {
        $middleware = new GuestOnlyMiddleware();
        $context = new AuthenticationContext();

        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withAttribute(AuthenticationAttributes::Context->value, $context)
        ;

        $response = $middleware->process(
            $request,
            new class($this->factory) implements RequestHandlerInterface {
                public function __construct(private readonly Psr17Factory $factory) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->factory->createResponse(200);
                }
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function throwsWhenUserIsAuthenticated(): void
    {
        $middleware = new GuestOnlyMiddleware();
        $context = new AuthenticationContext($this->createStub(TokenInterface::class));

        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withAttribute(AuthenticationAttributes::Context->value, $context)
        ;

        $this->expectException(AlreadyAuthenticatedException::class);
        $middleware->process(
            $request,
            new class($this->factory) implements RequestHandlerInterface {
                public function __construct(private readonly Psr17Factory $factory) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->factory->createResponse(200);
                }
            },
        );
    }

    #[Test]
    public function passesWhenNoContextAttribute(): void
    {
        $middleware = new GuestOnlyMiddleware();

        $response = $middleware->process(
            $this->factory->createServerRequest('GET', '/'),
            new class($this->factory) implements RequestHandlerInterface {
                public function __construct(private readonly Psr17Factory $factory) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->factory->createResponse(200);
                }
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
