<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Exception\UnknownAuthenticationProfileException;
use Sirix\Mezzio\Authentication\Factory\ProfileMiddlewareFactory;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;

final class ProfileMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function returnsAuthenticateMiddlewareFromNamedProfile(): void
    {
        $authenticateMiddleware = $this->createAuthenticateMiddleware();
        $profile                = $this->createStub(AuthenticationProfileInterface::class);
        $profile->method('authenticateMiddleware')->willReturn($authenticateMiddleware);

        $provider = $this->createStub(AuthenticationProfileProviderInterface::class);
        $provider->method('get')->willReturn($profile);

        $profileMiddlewareFactory = new ProfileMiddlewareFactory();
        $middleware               = $profileMiddlewareFactory->create(
            new ArrayContainer([
                AuthenticationProfileProviderInterface::class => $provider,
            ]),
            new MiddlewareSpecification(
                AuthenticateMiddleware::class,
                ProfileMiddlewareFactory::class,
                [
                    'profile' => 'api',
                ],
            ),
        );

        self::assertSame($authenticateMiddleware, $middleware);
    }

    #[Test]
    public function returnsOptionalAuthenticateMiddlewareFromNamedProfile(): void
    {
        $optionalAuthenticateMiddleware = $this->createOptionalAuthenticateMiddleware();
        $profile                        = $this->createStub(AuthenticationProfileInterface::class);
        $profile->method('optionalAuthenticateMiddleware')->willReturn($optionalAuthenticateMiddleware);

        $provider = $this->createStub(AuthenticationProfileProviderInterface::class);
        $provider->method('get')->willReturn($profile);

        $profileMiddlewareFactory = new ProfileMiddlewareFactory();
        $middleware               = $profileMiddlewareFactory->create(
            new ArrayContainer([
                AuthenticationProfileProviderInterface::class => $provider,
            ]),
            new MiddlewareSpecification(
                OptionalAuthenticateMiddleware::class,
                ProfileMiddlewareFactory::class,
                [
                    'profile' => 'web',
                ],
            ),
        );

        self::assertSame($optionalAuthenticateMiddleware, $middleware);
    }

    #[Test]
    public function propagatesUnknownProfileException(): void
    {
        $provider = $this->createStub(AuthenticationProfileProviderInterface::class);
        $provider->method('get')->willThrowException(
            UnknownAuthenticationProfileException::forName('missing', []),
        );

        $this->expectException(UnknownAuthenticationProfileException::class);

        (new ProfileMiddlewareFactory())->create(
            new ArrayContainer([
                AuthenticationProfileProviderInterface::class => $provider,
            ]),
            new MiddlewareSpecification(
                AuthenticateMiddleware::class,
                ProfileMiddlewareFactory::class,
                [
                    'profile' => 'missing',
                ],
            ),
        );
    }

    #[Test]
    public function throwsWhenProfileArgumentIsMissing(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('missing the required "profile" argument');

        (new ProfileMiddlewareFactory())->create(
            new ArrayContainer([]),
            new MiddlewareSpecification(
                AuthenticateMiddleware::class,
                ProfileMiddlewareFactory::class,
                [],
            ),
        );
    }

    #[Test]
    public function throwsWhenProfileArgumentIsEmpty(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('non-empty-string "profile" argument');

        (new ProfileMiddlewareFactory())->create(
            new ArrayContainer([]),
            new MiddlewareSpecification(
                AuthenticateMiddleware::class,
                ProfileMiddlewareFactory::class,
                [
                    'profile' => '',
                ],
            ),
        );
    }

    #[Test]
    public function throwsWhenServiceIsUnsupported(): void
    {
        $profile  = $this->createStub(AuthenticationProfileInterface::class);
        $provider = $this->createStub(AuthenticationProfileProviderInterface::class);
        $provider->method('get')->willReturn($profile);

        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('service "' . GuestOnlyMiddleware::class . '" is not supported');

        (new ProfileMiddlewareFactory())->create(
            new ArrayContainer([
                AuthenticationProfileProviderInterface::class => $provider,
            ]),
            new MiddlewareSpecification(
                GuestOnlyMiddleware::class,
                ProfileMiddlewareFactory::class,
                [
                    'profile' => 'web',
                ],
            ),
        );
    }

    private function createAuthenticateMiddleware(): AuthenticateMiddleware
    {
        return new AuthenticateMiddleware(
            $this->createStub(AuthenticatorInterface::class),
            $this->createStub(TokenStorageProviderInterface::class),
            $this->createStub(TokenTransportInterface::class),
            'api',
        );
    }

    private function createOptionalAuthenticateMiddleware(): OptionalAuthenticateMiddleware
    {
        return new OptionalAuthenticateMiddleware(
            $this->createStub(AuthenticatorInterface::class),
            $this->createStub(TokenStorageProviderInterface::class),
            $this->createStub(TokenTransportInterface::class),
            'web',
        );
    }
}
