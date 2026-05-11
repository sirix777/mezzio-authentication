<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;

final class OptionalAuthenticateMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): OptionalAuthenticateMiddleware
    {
        $config = $container->has('config')
            ? $container->get('config')
            : [];

        $authConfig = $config['authentication'] ?? [];
        $transportConfig = $authConfig['transport'] ?? [];
        $defaultStorage = (string) ($authConfig['default_storage'] ?? 'null');

        $storageForTransport = (string) ($transportConfig['storage'] ?? $defaultStorage);

        return new OptionalAuthenticateMiddleware(
            $container->get(AuthenticatorInterface::class),
            $container->get(TokenStorageProviderInterface::class),
            $container->get(TokenTransportInterface::class),
            $storageForTransport,
        );
    }
}
