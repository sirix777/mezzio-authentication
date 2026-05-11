<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Middleware;

use LogicException;
use Mezzio\Session\SessionInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationException;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\Storage\StorageException;
use Sirix\Mezzio\Authentication\TokenAuthenticator;
use Sirix\Mezzio\Authentication\TokenStorageProvider;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use SirixTest\Mezzio\Authentication\Support\InMemorySession;

final class AuthenticateMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function throwsWhenRequestIsUnauthenticated(): void
    {
        $provider = $this->createStub(AuthActorProviderInterface::class);
        $middleware = new AuthenticateMiddleware(
            new TokenAuthenticator($provider),
            new TokenStorageProvider('session', [
                'session' => new SessionTokenStorage(),
            ]),
            new BearerTokenTransport(),
            'session',
        );

        $this->expectException(AuthenticationException::class);
        $middleware->process(
            $this->factory->createServerRequest('GET', '/'),
            $this->createUnreachableHandler(),
        );
    }

    #[Test]
    public function passesAuthenticatedRequestThrough(): void
    {
        $session = new InMemorySession();
        $requestWithSession = $this->factory
            ->createServerRequest('GET', '/')
            ->withAttribute(SessionInterface::class, $session)
        ;

        $storage = new SessionTokenStorage();
        $token = $storage->create(['userId' => 42], null, $requestWithSession);
        $actor = $this->createStub(ActorInterface::class);

        $provider = $this->createMock(AuthActorProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('getActor')
            ->willReturn($actor)
        ;

        $middleware = new AuthenticateMiddleware(
            new TokenAuthenticator($provider),
            new TokenStorageProvider('session', ['session' => $storage]),
            new BearerTokenTransport(),
            'session',
        );

        $request = $requestWithSession
            ->withHeader('Authorization', 'Bearer ' . $token->getId())
        ;

        $response = $middleware->process(
            $request,
            new class($this->factory) implements RequestHandlerInterface {
                public function __construct(private readonly Psr17Factory $factory) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->factory->createResponse(204);
                }
            },
        );

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function treatsStorageErrorsAsUnauthenticatedRequest(): void
    {
        $provider = $this->createStub(AuthActorProviderInterface::class);
        $middleware = new AuthenticateMiddleware(
            new TokenAuthenticator($provider),
            new TokenStorageProvider('session', [
                'session' => new SessionTokenStorage(),
            ]),
            new BearerTokenTransport(),
            'session',
        );

        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Bearer broken-token')
        ;

        $this->expectException(AuthenticationException::class);

        try {
            $middleware->process($request, $this->createUnreachableHandler());
        } catch (StorageException $exception) {
            self::fail('StorageException should not leak from authentication middleware: ' . $exception->getMessage());
        }
    }

    private function createUnreachableHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new LogicException('Should not be called.');
            }
        };
    }
}
