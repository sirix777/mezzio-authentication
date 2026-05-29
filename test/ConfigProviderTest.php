<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Actor\GuestActor;
use Sirix\Mezzio\Authentication\Actor\NullActorProvider;
use Sirix\Mezzio\Authentication\ConfigProvider;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\SecurityActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;

final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function returnsDependenciesArray(): void
    {
        $configProvider = new ConfigProvider();
        $config = $configProvider();

        self::assertArrayHasKey('dependencies', $config);
        self::assertArrayHasKey('factories', $config['dependencies']);
        self::assertArrayHasKey('invokables', $config['dependencies']);
        self::assertArrayHasKey('aliases', $config['dependencies']);
    }

    #[Test]
    public function registersAuthManagerFactory(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        self::assertArrayHasKey(AuthManagerInterface::class, $dependencies['factories']);
    }

    #[Test]
    public function registersAuthenticatorFactory(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        self::assertArrayHasKey(AuthenticatorInterface::class, $dependencies['factories']);
    }

    #[Test]
    public function registersActorProviderFactories(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        self::assertArrayHasKey(AuthActorProviderInterface::class, $dependencies['factories']);
        self::assertArrayHasKey(SecurityActorProviderInterface::class, $dependencies['factories']);
        self::assertArrayHasKey(ActorInterface::class, $dependencies['factories']);
    }

    #[Test]
    public function registersTokenStorageProviderFactory(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        self::assertArrayHasKey(TokenStorageProviderInterface::class, $dependencies['factories']);
        self::assertArrayHasKey(SessionTokenStorage::class, $dependencies['factories']);
    }

    #[Test]
    public function registersTokenTransportFactory(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        self::assertArrayHasKey(TokenTransportInterface::class, $dependencies['factories']);
    }

    #[Test]
    public function registersMiddlewareFactories(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        self::assertArrayHasKey(AuthenticateMiddleware::class, $dependencies['factories']);
        self::assertArrayHasKey(OptionalAuthenticateMiddleware::class, $dependencies['factories']);
        self::assertArrayHasKey(GuestOnlyMiddleware::class, $dependencies['invokables']);
    }

    #[Test]
    public function registersInvokables(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        self::assertArrayHasKey(GuestActor::class, $dependencies['invokables']);
        self::assertArrayHasKey(NullActorProvider::class, $dependencies['invokables']);
        self::assertArrayHasKey(NullTokenStorage::class, $dependencies['invokables']);
        self::assertArrayHasKey(BearerTokenTransport::class, $dependencies['invokables']);
        self::assertArrayHasKey(GuestOnlyMiddleware::class, $dependencies['invokables']);
    }

    #[Test]
    public function registersAliases(): void
    {
        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        self::assertArrayHasKey(AuthContextInterface::class, $dependencies['aliases']);
    }
}
