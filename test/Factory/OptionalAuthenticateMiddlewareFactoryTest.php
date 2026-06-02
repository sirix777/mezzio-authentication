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
        $psr7Factory = new Psr7Factory();
        $serverRequest = $psr7Factory->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Bearer token-id')
        ;
        $response = $psr7Factory->createResponse(200);
        $authToken = new AuthToken('token-id', 'api', ['userId' => 1]);
        $actor = $this->createStub(ActorInterface::class);

        $storage = $this->createMock(TokenStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('load')
            ->with('token-id', $serverRequest)
            ->willReturn($authToken)
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
            ->with($serverRequest)
            ->willReturn('token-id')
        ;

        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticator
            ->expects($this->once())
            ->method('authenticate')
            ->with($authToken)
            ->willReturn(new AuthenticationContext($authToken, $actor))
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

        self::assertSame($response, $middleware->process($serverRequest, $handler));
    }
}
