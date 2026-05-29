<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Mezzio\Session\SessionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\TokenStorageProvider;

use function array_keys;
use function interface_exists;
use function is_string;

final class TokenStorageProviderFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): TokenStorageProviderInterface
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader = ConfigReader::fromContainer($containerResolver);
        $defaultStorage = $configReader->nonEmptyString('authentication.default_storage', 'null');

        $storages = [
            'null' => $containerResolver->get(NullTokenStorage::class),
        ];

        if (interface_exists(SessionInterface::class) && $containerResolver->has(SessionTokenStorage::class)) {
            $storages['session'] = $containerResolver->get(SessionTokenStorage::class);
        }

        foreach ($configReader->map('authentication.storages', []) as $name => $serviceId) {
            if (! is_string($serviceId) || '' === $serviceId) {
                throw InvalidConfigValueException::forType(
                    "authentication.storages.{$name}",
                    'non-empty-string',
                    $serviceId,
                    self::class,
                );
            }

            $storages[$name] = $containerResolver->getAs($serviceId, TokenStorageInterface::class);
        }

        if (! isset($storages[$defaultStorage])) {
            if ('session' === $defaultStorage && interface_exists(SessionInterface::class)) {
                throw MissingContainerServiceException::forService(SessionTokenStorage::class, self::class);
            }

            throw InvalidConfigValueException::forAllowedValues(
                'authentication.default_storage',
                array_keys($storages),
                $defaultStorage,
                self::class,
            );
        }

        return new TokenStorageProvider($defaultStorage, $storages);
    }
}
