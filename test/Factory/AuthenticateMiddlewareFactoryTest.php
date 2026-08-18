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
use Sirix\Mezzio\Authentication\Factory\AuthenticateMiddlewareFactory;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;

final class AuthenticateMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function returnsTheRequiredMiddlewareFromTheDefaultProfile(): void
    {
        $storageProvider             = $this->createStub(TokenStorageProviderInterface::class);
        $transport                   = $this->createStub(TokenTransportInterface::class);
        $authenticator               = $this->createStub(AuthenticatorInterface::class);
        $authenticateMiddleware      = new AuthenticateMiddleware(
            $authenticator,
            $storageProvider,
            $transport,
            'web',
        );
        $authenticationProfile = new AuthenticationProfile(
            'web',
            'web',
            $transport,
            new AuthenticationManager($storageProvider, $transport, 'web'),
            $authenticateMiddleware,
            new OptionalAuthenticateMiddleware(
                $authenticator,
                $storageProvider,
                $transport,
                'web',
            ),
        );

        self::assertSame($authenticateMiddleware, (new AuthenticateMiddlewareFactory())(new ArrayContainer([
            AuthenticationProfileProviderInterface::class => new AuthenticationProfileProvider([
                'web' => $authenticationProfile,
            ], $authenticationProfile),
        ])));
    }
}
