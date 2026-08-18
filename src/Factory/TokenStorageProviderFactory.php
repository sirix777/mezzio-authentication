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
use Sirix\Mezzio\Authentication\Config\AuthenticationProfileConfiguration;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\TokenStorageProvider;

use function array_keys;
use function interface_exists;
use function is_string;
use function trim;

final class TokenStorageProviderFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): TokenStorageProviderInterface
    {
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);
        $defaultStorage    = $configReader->nonEmptyString('authentication.default_storage', 'null');

        $storages = [
            'null' => $containerResolver->get(NullTokenStorage::class),
        ];

        if (interface_exists(SessionInterface::class) && $containerResolver->has(SessionTokenStorage::class)) {
            $storages['session'] = $containerResolver->get(SessionTokenStorage::class);
        }

        foreach ($configReader->map('authentication.storages', []) as $name => $serviceId) {
            if ('' === trim($name)) {
                throw InvalidConfigValueException::forType(
                    "authentication.storages.{$name}",
                    'non-empty-string storage name',
                    $name,
                    self::class,
                );
            }

            if (! is_string($serviceId) || '' === trim($serviceId)) {
                throw InvalidConfigValueException::forType(
                    "authentication.storages.{$name}",
                    'non-empty-string',
                    $serviceId,
                    self::class,
                );
            }

            $storages[$name] = $containerResolver->getAs(trim($serviceId), TokenStorageInterface::class);
        }

        $this->assertStorageIsRegistered($defaultStorage, 'authentication.default_storage', $storages);

        $transportStorage = $configReader->nonEmptyString('authentication.transport.storage', $defaultStorage);
        $this->assertStorageIsRegistered($transportStorage, 'authentication.transport.storage', $storages);

        foreach ((new AuthenticationProfileConfiguration($configReader))->profiles() as $authenticationProfileDefinition) {
            $this->assertStorageIsRegistered($authenticationProfileDefinition->storage(), $authenticationProfileDefinition->path() . '.storage', $storages);
        }

        return new TokenStorageProvider($defaultStorage, $storages);
    }

    /**
     * @param array<string, TokenStorageInterface> $storages
     */
    private function assertStorageIsRegistered(string $name, string $path, array $storages): void
    {
        if (isset($storages[$name])) {
            return;
        }

        if ('session' === $name && interface_exists(SessionInterface::class)) {
            throw MissingContainerServiceException::forService(SessionTokenStorage::class, $path);
        }

        throw InvalidConfigValueException::forAllowedValues(
            $path,
            $this->storageNames($storages),
            $name,
            self::class,
        );
    }

    /**
     * @param array<string, TokenStorageInterface> $storages
     *
     * @return non-empty-list<string>
     */
    private function storageNames(array $storages): array
    {
        return array_keys([
            'null' => true,
            ...$storages,
        ]);
    }
}
