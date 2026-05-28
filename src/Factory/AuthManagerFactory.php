<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\AuthenticationManager;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;

final class AuthManagerFactory
{
    public function __invoke(ContainerInterface $container): AuthManagerInterface
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);

        return new AuthenticationManager(
            $containerResolver->get(TokenStorageProviderInterface::class),
            $containerResolver->get(TokenTransportInterface::class),
        );
    }
}
