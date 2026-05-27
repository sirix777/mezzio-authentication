<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Factory\OptionalAuthenticateMiddlewareFactory;
use Sirix\Mezzio\Authentication\Token\AuthToken;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class OptionalAuthenticateMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function transportStorageOverridesDefaultStorage(): void
    {
        $factory = new Psr7Factory();
        $request = $factory->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Bearer token-id')
        ;
        $response = $factory->createResponse(200);
        $token = new AuthToken('token-id', 'api', ['userId' => 1]);
        $actor = $this->createStub(ActorInterface::class);

        $storage = $this->createMock(TokenStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('load')
            ->with('token-id', $request)
            ->willReturn($token)
        ;

        $storageProvider = $this->createMock(TokenStorageProviderInterface::class);
        $storageProvider
            ->expects($this->once())
            ->method('getStorage')
            ->with('api')
            ->willReturn($storage)
        ;

        $transport = $this->createMock(TokenTransportInterface::class);
        $transport
            ->expects($this->once())
            ->method('fetch')
            ->with($request)
            ->willReturn('token-id')
        ;

        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticator
            ->expects($this->once())
            ->method('authenticate')
            ->with($token)
            ->willReturn(new AuthenticationContext($token, $actor))
        ;

        $middleware = (new OptionalAuthenticateMiddlewareFactory())(new ArrayContainer([
            'config' => [
                'authentication' => [
                    'default_storage' => 'session',
                    'transport' => [
                        'storage' => 'api',
                    ],
                ],
            ],
            AuthenticatorInterface::class => $authenticator,
            TokenStorageProviderInterface::class => $storageProvider,
            TokenTransportInterface::class => $transport,
        ]));

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturn($response)
        ;

        self::assertSame($response, $middleware->process($request, $handler));
    }
}
