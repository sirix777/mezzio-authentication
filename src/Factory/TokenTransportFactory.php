<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use Sirix\Mezzio\Authentication\Transport\CookieTokenTransport;

final class TokenTransportFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): TokenTransportInterface
    {
        $configReader = ConfigReader::fromContainer(ContainerResolver::forFactory($container, self::class));

        return match ($configReader->stringEnum('authentication.transport.driver', ['bearer', 'cookie'], 'bearer')) {
            'cookie' => new CookieTokenTransport(
                name: $configReader->nonEmptyString('authentication.cookie.name', 'mezzio_authentication'),
                path: $configReader->nonEmptyString('authentication.cookie.path', '/'),
                domain: $configReader->optionalNonEmptyString('authentication.cookie.domain'),
                secure: $configReader->bool('authentication.cookie.secure', false),
                httpOnly: $configReader->bool('authentication.cookie.http_only', true),
                sameSite: $configReader->nonEmptyString('authentication.cookie.same_site', 'Lax'),
            ),
            default => new BearerTokenTransport(),
        };
    }
}
