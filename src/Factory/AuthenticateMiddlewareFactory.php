<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;

final class AuthenticateMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AuthenticateMiddleware
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader = ConfigReader::fromContainer($containerResolver);
        $defaultStorage = $configReader->nonEmptyString('authentication.default_storage', 'null');

        return new AuthenticateMiddleware(
            $containerResolver->get(AuthenticatorInterface::class),
            $containerResolver->get(TokenStorageProviderInterface::class),
            $containerResolver->get(TokenTransportInterface::class),
            $configReader->nonEmptyString('authentication.transport.storage', $defaultStorage),
        );
    }
}
