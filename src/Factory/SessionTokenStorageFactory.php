<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;

final class SessionTokenStorageFactory
{
    public function __invoke(ContainerInterface $container): SessionTokenStorage
    {
        $configReader = ConfigReader::fromContainer(ContainerResolver::forFactory($container, self::class));

        return new SessionTokenStorage(
            storage: 'session',
            prefix: $configReader->nonEmptyString('authentication.session.prefix', '_authentication.tokens.'),
        );
    }
}
