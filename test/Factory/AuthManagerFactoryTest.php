<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\AuthenticationManager;
use Sirix\Mezzio\Authentication\AuthenticationProfile;
use Sirix\Mezzio\Authentication\AuthenticationProfileProvider;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Factory\AuthenticateMiddlewareFactory;
use Sirix\Mezzio\Authentication\Factory\AuthenticationProfileProviderFactory;
use Sirix\Mezzio\Authentication\Factory\AuthManagerFactory;
use Sirix\Mezzio\Authentication\Factory\OptionalAuthenticateMiddlewareFactory;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\Token\AuthToken;
use Sirix\Mezzio\Authentication\TokenStorageProvider;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class AuthManagerFactoryTest extends TestCase
{
    #[Test]
    public function usesTheDefaultProfileManagerAndItsConfiguredStorageAndTransport(): void
    {
        $serverRequest  = (new Psr7Factory())->createServerRequest('POST', '/login');
        $response       = (new Psr7Factory())->createResponse();
        $authToken      = new AuthToken('api-token', 'api', []);

        $storage = $this->createMock(TokenStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('create')
            ->with([
                'userId' => 1,
            ], null, $serverRequest)
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
            ->method('attach')
            ->with($response, $authToken)
            ->willReturn($response)
        ;
        $authenticator               = $this->createStub(AuthenticatorInterface::class);
        $manager                     = new AuthenticationManager($storageProvider, $transport, 'api');
        $authenticationProfile       = new AuthenticationProfile(
            'api',
            'api',
            $transport,
            $manager,
            new AuthenticateMiddleware($authenticator, $storageProvider, $transport, 'api'),
            new OptionalAuthenticateMiddleware($authenticator, $storageProvider, $transport, 'api'),
        );
        $authenticationProfileProvider = new AuthenticationProfileProvider([
            'api' => $authenticationProfile,
        ], $authenticationProfile);

        $authenticationManager = (new AuthManagerFactory())(new ArrayContainer([
            AuthenticationProfileProviderInterface::class => $authenticationProfileProvider,
        ]));

        self::assertInstanceOf(AuthManagerInterface::class, $authenticationManager);
        self::assertSame($manager, $authenticationManager);
        self::assertSame($response, $authenticationManager->login($serverRequest, $response, [
            'userId' => 1,
        ]));
    }

    #[Test]
    public function usesTheSameNamedDefaultProfileBundleForAllUnqualifiedServices(): void
    {
        $tokenStorageProvider = new TokenStorageProvider('null', [
            'null' => new NullTokenStorage(),
        ]);
        $provider = (new AuthenticationProfileProviderFactory())(new ArrayContainer([
            'config'                             => [
                'authentication' => [
                    'default_profile' => 'api',
                    'profiles'        => [
                        'web' => [
                            'transport' => 'cookie',
                            'storage'   => 'null',
                        ],
                        'api' => [
                            'transport' => 'bearer',
                            'storage'   => 'null',
                        ],
                    ],
                ],
            ],
            AuthenticatorInterface::class        => $this->createStub(AuthenticatorInterface::class),
            TokenStorageProviderInterface::class => $tokenStorageProvider,
            TokenTransportInterface::class       => new BearerTokenTransport(),
        ]));
        $arrayContainer = new ArrayContainer([
            AuthenticationProfileProviderInterface::class => $provider,
        ]);
        $authenticationProfile = $provider->get('api');

        self::assertSame($authenticationProfile->manager(), (new AuthManagerFactory())($arrayContainer));
        self::assertSame($authenticationProfile->authenticateMiddleware(), (new AuthenticateMiddlewareFactory())($arrayContainer));
        self::assertSame($authenticationProfile->optionalAuthenticateMiddleware(), (new OptionalAuthenticateMiddlewareFactory())($arrayContainer));
    }
}
