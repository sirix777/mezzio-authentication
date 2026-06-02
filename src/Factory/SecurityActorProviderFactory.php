<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Actor\ContextActorProvider;
use Sirix\Mezzio\Authentication\Actor\GuestActor;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\SecurityActorProviderInterface;

final class SecurityActorProviderFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SecurityActorProviderInterface
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);

        return new ContextActorProvider(
            $containerResolver->get(AuthContextInterface::class),
            $containerResolver->get(GuestActor::class),
        );
    }
}
