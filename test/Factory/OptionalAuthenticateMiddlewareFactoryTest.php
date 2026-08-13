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
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Factory\OptionalAuthenticateMiddlewareFactory;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;

final class OptionalAuthenticateMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function returnsTheOptionalMiddlewareFromTheDefaultProfile(): void
    {
        $storageProvider                     = $this->createStub(TokenStorageProviderInterface::class);
        $transport                           = $this->createStub(TokenTransportInterface::class);
        $authenticator                       = $this->createStub(AuthenticatorInterface::class);
        $optionalAuthenticateMiddleware      = new OptionalAuthenticateMiddleware($authenticator, $storageProvider, $transport, 'api');
        $authenticationProfile               = new AuthenticationProfile(
            'api',
            'api',
            $transport,
            new AuthenticationManager($storageProvider, $transport, 'api'),
            new AuthenticateMiddleware($authenticator, $storageProvider, $transport, 'api'),
            $optionalAuthenticateMiddleware,
        );

        self::assertSame($optionalAuthenticateMiddleware, (new OptionalAuthenticateMiddlewareFactory())(new ArrayContainer([
            AuthenticationProfileProviderInterface::class => new AuthenticationProfileProvider([
                'api' => $authenticationProfile,
            ], $authenticationProfile),
        ])));
    }
}
