<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use InvalidArgumentException;
use Mezzio\Session\SessionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\TokenStorageProvider;

use function interface_exists;
use function is_array;
use function is_string;

final class TokenStorageProviderFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): TokenStorageProviderInterface
    {
        $config = $container->has('config')
            ? $container->get('config')
            : [];

        $authConfig = $config['authentication'] ?? [];
        $defaultStorage = (string) ($authConfig['default_storage'] ?? 'null');

        $storages = [
            'null' => $container->get(NullTokenStorage::class),
        ];

        if (interface_exists(SessionInterface::class) && $container->has(SessionTokenStorage::class)) {
            $storages['session'] = $container->get(SessionTokenStorage::class);
        }

        $configuredStorages = $authConfig['storages'] ?? [];

        if (is_array($configuredStorages)) {
            foreach ($configuredStorages as $name => $serviceId) {
                if (! is_string($name)) {
                    continue;
                }

                if ('' === $name) {
                    continue;
                }

                if (! is_string($serviceId)) {
                    continue;
                }

                if ('' === $serviceId) {
                    continue;
                }

                if (! $container->has($serviceId)) {
                    continue;
                }

                $storage = $container->get($serviceId);
                if (! $storage instanceof TokenStorageInterface) {
                    throw new InvalidArgumentException(
                        "Storage service '{$serviceId}' for '{$name}' must implement TokenStorageInterface.",
                    );
                }

                $storages[$name] = $storage;
            }
        }

        if (! isset($storages[$defaultStorage])) {
            $defaultStorage = 'null';
        }

        return new TokenStorageProvider($defaultStorage, $storages);
    }
}
