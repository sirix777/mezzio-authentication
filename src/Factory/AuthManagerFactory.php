<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

final class AuthManagerFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AuthManagerInterface
    {
        return ContainerResolver::forFactory($container, self::class)
            ->get(AuthenticationProfileProviderInterface::class)
            ->getDefaultProfile()
            ->manager()
        ;
    }
}
