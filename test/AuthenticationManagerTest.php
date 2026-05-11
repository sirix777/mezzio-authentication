<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication;

use Nyholm\Psr7\Factory\Psr17Factory;
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

final class AuthenticationManagerTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function contextFallsBackToGuestWhenRequestHasNoAuthAttributes(): void
    {
        $manager = new AuthenticationManager(
            $this->createStub(TokenStorageProviderInterface::class),
            $this->createStub(TokenTransportInterface::class),
        );

        $request = $this->factory->createServerRequest('GET', '/');

        self::assertTrue($manager->guest($request));
        self::assertFalse($manager->check($request));
        self::assertNull($manager->token($request));
        self::assertNull($manager->actor($request));
    }

    #[Test]
    public function readsAuthenticationDataFromRequestContextAttribute(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $actor = $this->createStub(ActorInterface::class);

        $manager = new AuthenticationManager(
            $this->createStub(TokenStorageProviderInterface::class),
            $this->createStub(TokenTransportInterface::class),
        );

        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withAttribute(
                AuthenticationAttributes::Context->value,
                new AuthenticationContext($token, $actor),
            )
        ;

        self::assertTrue($manager->check($request));
        self::assertFalse($manager->guest($request));
        self::assertSame($token, $manager->token($request));
        self::assertSame($actor, $manager->actor($request));
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

        $response = $this->factory->createResponse(204);
        $detached = $this->createMock(ResponseInterface::class);

        $transport = $this->createMock(TokenTransportInterface::class);
        $transport
            ->expects($this->once())
            ->method('detach')
            ->with($response)
            ->willReturn($detached)
        ;

        $manager = new AuthenticationManager($storageProvider, $transport);

        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withAttribute(
                AuthenticationAttributes::Context->value,
                new AuthenticationContext($token),
            )
        ;

        self::assertSame($detached, $manager->logout($request, $response));
    }
}
