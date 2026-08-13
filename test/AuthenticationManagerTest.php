<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication;

use Mezzio\Session\SessionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\AuthenticationManager;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\TokenAuthenticator;
use Sirix\Mezzio\Authentication\TokenStorageProvider;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use SirixTest\Mezzio\Authentication\Support\InMemorySession;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class AuthenticationManagerTest extends TestCase
{
    private Psr7Factory $psr7Factory;

    protected function setUp(): void
    {
        $this->psr7Factory = new Psr7Factory();
    }

    #[Test]
    public function contextFallsBackToGuestWhenRequestHasNoAuthAttributes(): void
    {
        $authenticationManager = new AuthenticationManager(
            $this->createStub(TokenStorageProviderInterface::class),
            $this->createStub(TokenTransportInterface::class),
        );

        $serverRequest = $this->psr7Factory->createServerRequest('GET', '/');

        self::assertTrue($authenticationManager->guest($serverRequest));
        self::assertFalse($authenticationManager->check($serverRequest));
        self::assertNull($authenticationManager->token($serverRequest));
        self::assertNull($authenticationManager->actor($serverRequest));
    }

    #[Test]
    public function readsAuthenticationDataFromRequestContextAttribute(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $actor = $this->createStub(ActorInterface::class);

        $authenticationManager = new AuthenticationManager(
            $this->createStub(TokenStorageProviderInterface::class),
            $this->createStub(TokenTransportInterface::class),
        );

        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute(
                AuthenticationAttributes::Context->value,
                new AuthenticationContext($token, $actor),
            )
        ;

        self::assertTrue($authenticationManager->check($serverRequest));
        self::assertFalse($authenticationManager->guest($serverRequest));
        self::assertSame($token, $authenticationManager->token($serverRequest));
        self::assertSame($actor, $authenticationManager->actor($serverRequest));
    }

    #[Test]
    public function logoutDeletesTokenAndDetachesTransport(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getStorage')->willReturn('session');

        $storage = $this->createMock(TokenStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('delete')
            ->with($token, $this->isInstanceOf(ServerRequestInterface::class))
        ;

        $storageProvider = $this->createMock(TokenStorageProviderInterface::class);
        $storageProvider
            ->expects($this->once())
            ->method('getStorage')
            ->with('session')
            ->willReturn($storage)
        ;

        $response = $this->psr7Factory->createResponse(204);
        $detached = $this->createMock(ResponseInterface::class);

        $transport = $this->createMock(TokenTransportInterface::class);
        $transport
            ->expects($this->once())
            ->method('detach')
            ->with($response)
            ->willReturn($detached)
        ;

        $authenticationManager = new AuthenticationManager($storageProvider, $transport, 'session');

        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute(
                AuthenticationAttributes::Context->value,
                new AuthenticationContext($token, $this->createStub(ActorInterface::class)),
            )
        ;

        self::assertSame($detached, $authenticationManager->logout($serverRequest, $response));
    }

    #[Test]
    public function completesSessionLoginAuthenticationAndLogoutLifecycle(): void
    {
        $inMemorySession = new InMemorySession();
        $request         = $this->psr7Factory
            ->createServerRequest('POST', '/login')
            ->withAttribute(SessionInterface::class, $inMemorySession)
        ;
        $sessionTokenStorage  = new SessionTokenStorage();
        $tokenStorageProvider = new TokenStorageProvider('session', [
            'session' => $sessionTokenStorage,
        ]);
        $authenticationManager  = new AuthenticationManager($tokenStorageProvider, new BearerTokenTransport(), 'session');

        $loginResponse = $authenticationManager->login(
            $request,
            $this->psr7Factory->createResponse(204),
            [
                'userId' => 42,
                'roles'  => ['user'],
            ],
        );

        $tokenId = (new BearerTokenTransport())->fetch(
            $request->withHeader('Authorization', $loginResponse->getHeaderLine('Authorization')),
        );
        self::assertNotNull($tokenId);

        $actor                  = $this->createStub(ActorInterface::class);
        $authenticateMiddleware = new AuthenticateMiddleware(
            new TokenAuthenticator(new class($actor) implements AuthActorProviderInterface {
                public function __construct(private readonly ActorInterface $actor) {}

                public function getActor(TokenInterface $token): ActorInterface
                {
                    return $this->actor;
                }
            }),
            $tokenStorageProvider,
            new BearerTokenTransport(),
            'session',
        );
        $handler = new class($this->psr7Factory) implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function __construct(private readonly Psr7Factory $psr7Factory) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return $this->psr7Factory->createResponse(200);
            }
        };

        $authenticateMiddleware->process($request->withHeader('Authorization', 'Bearer ' . $tokenId), $handler);

        self::assertInstanceOf(ServerRequestInterface::class, $handler->request);
        self::assertTrue($authenticationManager->check($handler->request));
        self::assertSame($actor, $authenticationManager->actor($handler->request));

        $logoutResponse = $authenticationManager->logout($handler->request, $loginResponse);

        self::assertSame('', $logoutResponse->getHeaderLine('Authorization'));
        self::assertNull($sessionTokenStorage->load($tokenId, $request));
    }
}
