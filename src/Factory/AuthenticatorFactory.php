<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\TokenAuthenticator;

final class AuthenticatorFactory
{
    public function __invoke(ContainerInterface $container): AuthenticatorInterface
    {
        return new TokenAuthenticator(
            ContainerResolver::forFactory($container, self::class)->get(AuthActorProviderInterface::class),
        );
    }
}
