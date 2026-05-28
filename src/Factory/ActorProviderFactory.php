<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Actor\PayloadActorProvider;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;

final class ActorProviderFactory
{
    public function __invoke(ContainerInterface $container): AuthActorProviderInterface
    {
        $configReader = ConfigReader::fromContainer(ContainerResolver::forFactory($container, self::class));

        return new PayloadActorProvider(
            $configReader->nonEmptyString('authentication.actor.roles_key', 'roles'),
            $configReader->nonEmptyString('authentication.actor.role_key', 'role'),
        );
    }
}
