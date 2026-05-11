<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use Sirix\Mezzio\Authentication\Transport\CookieTokenTransport;

use function is_string;

final class TokenTransportFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): TokenTransportInterface
    {
        $config = $container->has('config')
            ? $container->get('config')
            : [];

        $authConfig = $config['authentication'] ?? [];
        $transportConfig = $authConfig['transport'] ?? [];
        $driver = (string) ($transportConfig['driver'] ?? 'bearer');

        return match ($driver) {
            'cookie' => new CookieTokenTransport(
                name: (string) ($authConfig['cookie']['name'] ?? 'mezzio_authentication'),
                path: (string) ($authConfig['cookie']['path'] ?? '/'),
                domain: $this->optionalString($authConfig['cookie']['domain'] ?? null),
                secure: (bool) ($authConfig['cookie']['secure'] ?? false),
                httpOnly: (bool) ($authConfig['cookie']['http_only'] ?? true),
                sameSite: (string) ($authConfig['cookie']['same_site'] ?? 'Lax'),
            ),
            default => new BearerTokenTransport(),
        };
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && '' !== $value ? $value : null;
    }
}
