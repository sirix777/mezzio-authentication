<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Exception\AlreadyAuthenticatedException;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class GuestOnlyMiddlewareTest extends TestCase
{
    private Psr7Factory $psr7Factory;

    protected function setUp(): void
    {
        $this->psr7Factory = new Psr7Factory();
    }

    #[Test]
    public function passesWhenUserIsGuest(): void
    {
        $guestOnlyMiddleware = new GuestOnlyMiddleware();
        $authenticationContext = new AuthenticationContext();

        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute(AuthenticationAttributes::Context->value, $authenticationContext)
        ;

        $response = $guestOnlyMiddleware->process(
            $serverRequest,
            new class($this->psr7Factory) implements RequestHandlerInterface {
                public function __construct(private readonly Psr7Factory $psr7Factory) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->psr7Factory->createResponse(200);
                }
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function throwsWhenUserIsAuthenticated(): void
    {
        $guestOnlyMiddleware = new GuestOnlyMiddleware();
        $authenticationContext = new AuthenticationContext($this->createStub(TokenInterface::class));

        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute(AuthenticationAttributes::Context->value, $authenticationContext)
        ;

        $this->expectException(AlreadyAuthenticatedException::class);
        $guestOnlyMiddleware->process(
            $serverRequest,
            new class($this->psr7Factory) implements RequestHandlerInterface {
                public function __construct(private readonly Psr7Factory $psr7Factory) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->psr7Factory->createResponse(200);
                }
            },
        );
    }

    #[Test]
    public function passesWhenNoContextAttribute(): void
    {
        $guestOnlyMiddleware = new GuestOnlyMiddleware();

        $response = $guestOnlyMiddleware->process(
            $this->psr7Factory->createServerRequest('GET', '/'),
            new class($this->psr7Factory) implements RequestHandlerInterface {
                public function __construct(private readonly Psr7Factory $psr7Factory) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->psr7Factory->createResponse(200);
                }
            },
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
