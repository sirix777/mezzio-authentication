<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\AuthenticationManager;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;

final class AuthManagerFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AuthManagerInterface
    {
        $containerResolver    = ContainerResolver::forFactory($container, self::class);
        $configReader         = ConfigReader::fromContainer($containerResolver);
        $defaultStorage       = $configReader->nonEmptyString('authentication.default_storage', 'null');
        $transportStorage     = $configReader->nonEmptyString('authentication.transport.storage', $defaultStorage);

        return new AuthenticationManager(
            $containerResolver->get(TokenStorageProviderInterface::class),
            $containerResolver->get(TokenTransportInterface::class),
            $transportStorage,
        );
    }
}
