<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\AuthenticationManager;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
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

        $authenticationManager = new AuthenticationManager($storageProvider, $transport);

        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute(
                AuthenticationAttributes::Context->value,
                new AuthenticationContext($token),
            )
        ;

        self::assertSame($detached, $authenticationManager->logout($serverRequest, $response));
    }
}
