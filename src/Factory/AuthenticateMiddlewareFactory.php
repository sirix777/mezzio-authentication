<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;

final class AuthenticateMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AuthenticateMiddleware
    {
        return ContainerResolver::forFactory($container, self::class)
            ->get(AuthenticationProfileProviderInterface::class)
            ->getDefaultProfile()
            ->authenticateMiddleware()
        ;
    }
}
