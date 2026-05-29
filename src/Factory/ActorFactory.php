<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\SecurityActorProviderInterface;

final class ActorFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ActorInterface
    {
        return ContainerResolver::forFactory($container, self::class)
            ->get(SecurityActorProviderInterface::class)
            ->getActor()
        ;
    }
}
