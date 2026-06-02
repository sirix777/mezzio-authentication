<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Middleware;

use LogicException;
use Mezzio\Session\SessionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Exception\AuthenticationException;
use Sirix\Mezzio\Authentication\Exception\StorageException;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\TokenAuthenticator;
use Sirix\Mezzio\Authentication\TokenStorageProvider;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use SirixTest\Mezzio\Authentication\Support\InMemorySession;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class AuthenticateMiddlewareTest extends TestCase
{
    private Psr7Factory $psr7Factory;

    protected function setUp(): void
    {
        $this->psr7Factory = new Psr7Factory();
    }

    #[Test]
    public function throwsWhenRequestIsUnauthenticated(): void
    {
        $provider = $this->createStub(AuthActorProviderInterface::class);
        $authenticateMiddleware = new AuthenticateMiddleware(
            new TokenAuthenticator($provider),
            new TokenStorageProvider('session', [
                'session' => new SessionTokenStorage(),
            ]),
            new BearerTokenTransport(),
            'session',
        );

        $this->expectException(AuthenticationException::class);
        $authenticateMiddleware->process(
            $this->psr7Factory->createServerRequest('GET', '/'),
            $this->createUnreachableHandler(),
        );
    }

    #[Test]
    public function passesAuthenticatedRequestThrough(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute(SessionInterface::class, $inMemorySession)
        ;

        $sessionTokenStorage = new SessionTokenStorage();
        $token = $sessionTokenStorage->create(['userId' => 42], null, $serverRequest);
        $actor = $this->createStub(ActorInterface::class);

        $provider = $this->createMock(AuthActorProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('getActor')
            ->willReturn($actor)
        ;

        $authenticateMiddleware = new AuthenticateMiddleware(
            new TokenAuthenticator($provider),
            new TokenStorageProvider('session', ['session' => $sessionTokenStorage]),
            new BearerTokenTransport(),
            'session',
        );

        $request = $serverRequest
            ->withHeader('Authorization', 'Bearer ' . $token->getId())
        ;

        $response = $authenticateMiddleware->process(
            $request,
            new class($this->psr7Factory) implements RequestHandlerInterface {
                public function __construct(private readonly Psr7Factory $psr7Factory) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->psr7Factory->createResponse(204);
                }
            },
        );

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function writesAuthenticationAttributesOnAuthenticatedRequest(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute(SessionInterface::class, $inMemorySession)
        ;

        $sessionTokenStorage = new SessionTokenStorage();
        $token = $sessionTokenStorage->create(['userId' => 42], null, $serverRequest);
        $actor = $this->createStub(ActorInterface::class);

        $provider = $this->createMock(AuthActorProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('getActor')
            ->with($token)
            ->willReturn($actor)
        ;

        $authenticateMiddleware = new AuthenticateMiddleware(
            new TokenAuthenticator($provider),
            new TokenStorageProvider('session', ['session' => $sessionTokenStorage]),
            new BearerTokenTransport(),
            'session',
        );

        $handler = new class($this->psr7Factory) implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function __construct(private readonly Psr7Factory $psr7Factory) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return $this->psr7Factory->createResponse(204);
            }
        };

        $authenticateMiddleware->process(
            $serverRequest->withHeader('Authorization', 'Bearer ' . $token->getId()),
            $handler,
        );

        $handledRequest = $handler->request;
        self::assertInstanceOf(ServerRequestInterface::class, $handledRequest);

        $attributes = $handledRequest->getAttributes();
        self::assertArrayHasKey(AuthenticationAttributes::Context->value, $attributes);
        self::assertArrayHasKey(AuthenticationAttributes::Token->value, $attributes);
        self::assertArrayHasKey(AuthenticationAttributes::Actor->value, $attributes);

        $context = $attributes[AuthenticationAttributes::Context->value];
        self::assertInstanceOf(AuthContextInterface::class, $context);

        $resolvedToken = $attributes[AuthenticationAttributes::Token->value];
        self::assertInstanceOf(TokenInterface::class, $resolvedToken);
        self::assertSame($token->getId(), $resolvedToken->getId());
        self::assertSame($token->getStorage(), $resolvedToken->getStorage());
        self::assertSame($token->getPayload(), $resolvedToken->getPayload());
        self::assertSame($actor, $attributes[AuthenticationAttributes::Actor->value]);
        self::assertSame($resolvedToken, $context->token());
        self::assertSame($actor, $context->actor());
    }

    #[Test]
    public function treatsStorageErrorsAsUnauthenticatedRequest(): void
    {
        $provider = $this->createStub(AuthActorProviderInterface::class);
        $authenticateMiddleware = new AuthenticateMiddleware(
            new TokenAuthenticator($provider),
            new TokenStorageProvider('session', [
                'session' => new SessionTokenStorage(),
            ]),
            new BearerTokenTransport(),
            'session',
        );

        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Bearer broken-token')
        ;

        $this->expectException(AuthenticationException::class);

        try {
            $authenticateMiddleware->process($serverRequest, $this->createUnreachableHandler());
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
