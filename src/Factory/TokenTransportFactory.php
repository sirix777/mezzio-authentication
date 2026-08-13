<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Config\AuthenticationProfileConfiguration;
use Sirix\Mezzio\Authentication\Config\TokenTransportResolver;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;

final class TokenTransportFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): TokenTransportInterface
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);

        return (new TokenTransportResolver(
            $container,
            $configReader,
            new AuthenticationProfileConfiguration($configReader),
        ))->legacyTransport();
    }
}
