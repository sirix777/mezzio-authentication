<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Config;

use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use Sirix\Mezzio\Authentication\Transport\CookieTokenTransport;

use function array_key_exists;
use function array_keys;
use function in_array;
use function is_bool;
use function is_string;
use function trim;

/**
 * @internal
 */
final readonly class TokenTransportResolver
{
    public function __construct(
        private ContainerInterface $container,
        private ConfigReader $configReader,
        private AuthenticationProfileConfiguration $authenticationProfileConfiguration,
    ) {}

    public function legacyTransport(): TokenTransportInterface
    {
        return match ($this->configReader->stringEnum('authentication.transport.driver', ['bearer', 'cookie'], 'bearer')) {
            'cookie' => $this->cookieTransport([]),
            default  => $this->bearerTransport([]),
        };
    }

    public function profileTransport(AuthenticationProfileDefinition $authenticationProfileDefinition): TokenTransportInterface
    {
        $driver                  = $authenticationProfileDefinition->transport();
        $customTransportServices = $this->authenticationProfileConfiguration->customTransportServiceIds();

        if ('cookie' === $driver) {
            return $this->cookieTransport(
                $authenticationProfileDefinition->transportOptions(),
                $authenticationProfileDefinition->path() . '.transport_options',
            );
        }

        if ('bearer' === $driver) {
            return $this->bearerTransport(
                $authenticationProfileDefinition->transportOptions(),
                $authenticationProfileDefinition->path() . '.transport_options',
            );
        }

        if (! array_key_exists($driver, $customTransportServices)) {
            throw InvalidConfigValueException::forAllowedValues(
                $authenticationProfileDefinition->path() . '.transport',
                ['bearer', 'cookie', ...array_keys($customTransportServices)],
                $driver,
                self::class,
            );
        }

        if ($authenticationProfileDefinition->hasTransportOptions()) {
            throw InvalidConfigValueException::forType(
                $authenticationProfileDefinition->path() . '.transport_options',
                'absent for custom transports',
                $authenticationProfileDefinition->transportOptions(),
                self::class,
            );
        }

        return ContainerResolver::forContext(
            $this->container,
            $authenticationProfileDefinition->path() . '.transport',
        )->getAs($customTransportServices[$driver], TokenTransportInterface::class);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function bearerTransport(array $options, string $path = 'authentication.bearer'): BearerTokenTransport
    {
        $this->assertSupportedOptions($options, $path, ['header', 'scheme']);

        return new BearerTokenTransport(
            header: $this->nonEmptyStringOption(
                $options,
                'header',
                $path,
                $this->configReader->nonEmptyString('authentication.bearer.header', 'Authorization'),
            ),
            scheme: $this->nonEmptyStringOption(
                $options,
                'scheme',
                $path,
                $this->configReader->nonEmptyString('authentication.bearer.scheme', 'Bearer'),
            ),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function cookieTransport(array $options, string $path = 'authentication.cookie'): CookieTokenTransport
    {
        $this->assertSupportedOptions($options, $path, ['name', 'path', 'domain', 'secure', 'http_only', 'same_site']);

        return new CookieTokenTransport(
            name: $this->nonEmptyStringOption(
                $options,
                'name',
                $path,
                $this->configReader->nonEmptyString('authentication.cookie.name', 'sirix_authentication'),
            ),
            path: $this->nonEmptyStringOption(
                $options,
                'path',
                $path,
                $this->configReader->nonEmptyString('authentication.cookie.path', '/'),
            ),
            domain: $this->optionalStringOption(
                $options,
                'domain',
                $path,
                $this->configReader->optionalNonEmptyString('authentication.cookie.domain'),
            ),
            secure: $this->boolOption(
                $options,
                'secure',
                $path,
                $this->configReader->bool('authentication.cookie.secure', false),
            ),
            httpOnly: $this->boolOption(
                $options,
                'http_only',
                $path,
                $this->configReader->bool('authentication.cookie.http_only', true),
            ),
            sameSite: $this->nonEmptyStringOption(
                $options,
                'same_site',
                $path,
                $this->configReader->nonEmptyString('authentication.cookie.same_site', 'Lax'),
            ),
        );
    }

    /**
     * @param array<string, mixed>   $options
     * @param non-empty-list<string> $allowed
     */
    private function assertSupportedOptions(array $options, string $path, array $allowed): void
    {
        foreach ($options as $option => $_) {
            if (! in_array($option, $allowed, true)) {
                throw InvalidConfigValueException::forAllowedValues($path . '.' . $option, $allowed, $option, self::class);
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function nonEmptyStringOption(array $options, string $name, string $path, string $default): string
    {
        if (! array_key_exists($name, $options)) {
            return $default;
        }

        $value = $options[$name];
        if (! is_string($value) || '' === trim($value)) {
            throw InvalidConfigValueException::forType($path . '.' . $name, 'non-empty-string', $value, self::class);
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function optionalStringOption(array $options, string $name, string $path, ?string $default): ?string
    {
        if (! array_key_exists($name, $options)) {
            return $default;
        }

        $value = $options[$name];
        if (! is_string($value)) {
            throw InvalidConfigValueException::forType($path . '.' . $name, 'string', $value, self::class);
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function boolOption(array $options, string $name, string $path, bool $default): bool
    {
        if (! array_key_exists($name, $options)) {
            return $default;
        }

        $value = $options[$name];
        if (! is_bool($value)) {
            throw InvalidConfigValueException::forType($path . '.' . $name, 'bool', $value, self::class);
        }

        return $value;
    }
}
