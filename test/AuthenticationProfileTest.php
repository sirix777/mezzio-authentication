<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\AuthenticationManager;
use Sirix\Mezzio\Authentication\AuthenticationProfile;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;

final class AuthenticationProfileTest extends TestCase
{
    #[Test]
    public function exposesTheBundledObjectsByIdentity(): void
    {
        $transport                      = $this->createStub(TokenTransportInterface::class);
        $storageProvider                = $this->createStub(TokenStorageProviderInterface::class);
        $authenticator                  = $this->createStub(AuthenticatorInterface::class);
        $authenticationManager          = new AuthenticationManager($storageProvider, $transport, 'redis');
        $authenticateMiddleware         = new AuthenticateMiddleware($authenticator, $storageProvider, $transport, 'redis');
        $optionalAuthenticateMiddleware = new OptionalAuthenticateMiddleware($authenticator, $storageProvider, $transport, 'redis');
        $authenticationProfile          = new AuthenticationProfile(
            'api',
            'redis',
            $transport,
            $authenticationManager,
            $authenticateMiddleware,
            $optionalAuthenticateMiddleware,
        );

        self::assertSame('api', $authenticationProfile->name());
        self::assertSame('redis', $authenticationProfile->storageName());
        self::assertSame($transport, $authenticationProfile->transport());
        self::assertSame($authenticationManager, $authenticationProfile->manager());
        self::assertSame($authenticateMiddleware, $authenticationProfile->authenticateMiddleware());
        self::assertSame($optionalAuthenticateMiddleware, $authenticationProfile->optionalAuthenticateMiddleware());
    }
}
