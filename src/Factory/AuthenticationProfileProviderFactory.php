<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use InvalidArgumentException;
use Mezzio\Session\SessionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use Sirix\Mezzio\Authentication\AuthenticationManager;
use Sirix\Mezzio\Authentication\AuthenticationProfile;
use Sirix\Mezzio\Authentication\AuthenticationProfileProvider;
use Sirix\Mezzio\Authentication\Config\AuthenticationProfileConfiguration;
use Sirix\Mezzio\Authentication\Config\AuthenticationProfileDefinition;
use Sirix\Mezzio\Authentication\Config\TokenTransportResolver;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;

use function array_key_exists;
use function array_keys;
use function interface_exists;

final class AuthenticationProfileProviderFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AuthenticationProfileProviderInterface
    {
        $containerResolver                  = ContainerResolver::forFactory($container, self::class);
        $configReader                       = ConfigReader::fromContainer($containerResolver);
        $authenticationProfileConfiguration = new AuthenticationProfileConfiguration($configReader);
        $tokenStorageProvider               = $containerResolver->get(TokenStorageProviderInterface::class);
        $authenticator                      = $containerResolver->get(AuthenticatorInterface::class);
        $tokenTransportResolver             = new TokenTransportResolver($container, $configReader, $authenticationProfileConfiguration);

        $authenticationProfileConfiguration->customTransportServiceIds();

        $profiles = [];
        foreach ($authenticationProfileConfiguration->profiles() as $name => $authenticationProfileDefinition) {
            $this->validateStorage(
                $tokenStorageProvider,
                $authenticationProfileDefinition->storage(),
                $authenticationProfileDefinition->path() . '.storage',
                $containerResolver,
            );
            $profiles[$name] = $this->createProfile(
                $authenticationProfileDefinition,
                $tokenTransportResolver->profileTransport($authenticationProfileDefinition),
                $authenticator,
                $tokenStorageProvider,
            );
        }

        $defaultName = $authenticationProfileConfiguration->defaultProfileName();

        if (null === $defaultName) {
            return new AuthenticationProfileProvider(
                $profiles,
                $this->createLegacyProfile(
                    $configReader,
                    $containerResolver->get(TokenTransportInterface::class),
                    $authenticator,
                    $tokenStorageProvider,
                    $containerResolver,
                ),
            );
        }

        if (! array_key_exists($defaultName, $profiles)) {
            throw InvalidConfigValueException::forAllowedValues(
                'authentication.default_profile',
                [] === $profiles ? ['configured profile name'] : array_keys($profiles),
                $defaultName,
                self::class,
            );
        }

        return new AuthenticationProfileProvider($profiles, $profiles[$defaultName]);
    }

    private function createLegacyProfile(
        ConfigReader $configReader,
        TokenTransportInterface $tokenTransport,
        AuthenticatorInterface $authenticator,
        TokenStorageProviderInterface $tokenStorageProvider,
        ContainerResolver $containerResolver,
    ): AuthenticationProfileInterface {
        $defaultStorage   = $configReader->nonEmptyString('authentication.default_storage', 'null');
        $transportStorage = $configReader->nonEmptyString('authentication.transport.storage', $defaultStorage);

        $this->validateStorage($tokenStorageProvider, $transportStorage, 'authentication.transport.storage', $containerResolver);

        return $this->createProfile(
            new AuthenticationProfileDefinition('legacy', 'legacy', $transportStorage, [], false),
            $tokenTransport,
            $authenticator,
            $tokenStorageProvider,
        );
    }

    private function validateStorage(
        TokenStorageProviderInterface $tokenStorageProvider,
        string $storage,
        string $path,
        ContainerResolver $containerResolver,
    ): void {
        try {
            $tokenStorageProvider->getStorage($storage);
        } catch (InvalidArgumentException $exception) {
            if ('session' === $storage && interface_exists(SessionInterface::class) && ! $containerResolver->has(SessionTokenStorage::class)) {
                throw MissingContainerServiceException::forService(SessionTokenStorage::class, $path, $exception);
            }

            throw InvalidConfigValueException::forAllowedValues(
                $path,
                ['registered storage'],
                $storage,
                self::class,
            );
        }
    }

    private function createProfile(
        AuthenticationProfileDefinition $authenticationProfileDefinition,
        TokenTransportInterface $tokenTransport,
        AuthenticatorInterface $authenticator,
        TokenStorageProviderInterface $tokenStorageProvider,
    ): AuthenticationProfileInterface {
        $storage = $authenticationProfileDefinition->storage();

        return new AuthenticationProfile(
            $authenticationProfileDefinition->name(),
            $storage,
            $tokenTransport,
            new AuthenticationManager($tokenStorageProvider, $tokenTransport, $storage),
            new AuthenticateMiddleware($authenticator, $tokenStorageProvider, $tokenTransport, $storage),
            new OptionalAuthenticateMiddleware($authenticator, $tokenStorageProvider, $tokenTransport, $storage),
        );
    }
}
