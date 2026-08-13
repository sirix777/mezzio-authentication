<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;

final class OptionalAuthenticateMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): OptionalAuthenticateMiddleware
    {
        return ContainerResolver::forFactory($container, self::class)
            ->get(AuthenticationProfileProviderInterface::class)
            ->getDefaultProfile()
            ->optionalAuthenticateMiddleware()
        ;
    }
}
