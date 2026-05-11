<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;

final class SessionTokenStorageFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SessionTokenStorage
    {
        $config = $container->has('config')
            ? $container->get('config')
            : [];

        $authConfig = $config['authentication'] ?? [];
        $sessionConfig = $authConfig['session'] ?? [];

        return new SessionTokenStorage(
            storage: 'session',
            prefix: (string) ($sessionConfig['prefix'] ?? '_authentication.tokens.'),
        );
    }
}
