<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Mezzio\Authentication\Actor\PayloadActorProvider;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;

use function is_string;

final class ActorProviderFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AuthActorProviderInterface
    {
        $config = $container->has('config')
            ? $container->get('config')
            : [];

        $authConfig = $config['authentication'] ?? [];

        $rolesKey = (string) ($authConfig['actor']['roles_key'] ?? 'roles');
        $roleKey = $authConfig['actor']['role_key'] ?? null;
        $roleKey = is_string($roleKey) && '' !== $roleKey
            ? $roleKey
            : 'role';

        return new PayloadActorProvider($rolesKey, $roleKey);
    }
}
