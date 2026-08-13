<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Config;

use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;

use function array_key_exists;
use function is_array;
use function is_string;
use function strrpos;
use function substr;
use function trim;

/**
 * @internal
 */
final readonly class AuthenticationProfileConfiguration
{
    public function __construct(private ConfigReader $configReader) {}

    /**
     * @return array<string, AuthenticationProfileDefinition>
     */
    public function profiles(): array
    {
        $profiles = [];

        foreach ($this->configReader->map('authentication.profiles', []) as $name => $definition) {
            $path = 'authentication.profiles.' . $name;

            if ('' === trim($name)) {
                throw InvalidConfigValueException::forType($path, 'non-empty-string profile name', $name, self::class);
            }

            $definition = $this->map($path, $definition);

            $optionsPath = $path . '.transport_options';
            $hasOptions  = array_key_exists('transport_options', $definition);

            $profiles[$name] = new AuthenticationProfileDefinition(
                $name,
                $this->requiredNonEmptyString($definition, $path . '.transport'),
                $this->requiredNonEmptyString($definition, $path . '.storage'),
                $hasOptions ? $this->map($optionsPath, $definition['transport_options']) : [],
                $hasOptions,
            );
        }

        return $profiles;
    }

    public function defaultProfileName(): ?string
    {
        if (! $this->configReader->has('authentication.default_profile')) {
            return null;
        }

        return $this->configReader->requiredNonEmptyString('authentication.default_profile');
    }

    /**
     * @return array<string, string>
     */
    public function customTransportServiceIds(): array
    {
        $transports = [];

        foreach ($this->configReader->map('authentication.transports', []) as $name => $serviceId) {
            $path = 'authentication.transports.' . $name;

            if ('' === trim($name) || 'bearer' === $name || 'cookie' === $name) {
                throw InvalidConfigValueException::forType($path, 'non-reserved non-empty-string transport name', $name, self::class);
            }

            if (! is_string($serviceId) || '' === trim($serviceId)) {
                throw InvalidConfigValueException::forType($path, 'non-empty-string', $serviceId, self::class);
            }

            $transports[$name] = trim($serviceId);
        }

        return $transports;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function requiredNonEmptyString(array $definition, string $path): string
    {
        $key = substr($path, strrpos($path, '.') + 1);

        if (! array_key_exists($key, $definition) || ! is_string($definition[$key]) || '' === trim($definition[$key])) {
            throw InvalidConfigValueException::forType($path, 'non-empty-string', $definition[$key] ?? null, self::class);
        }

        return trim($definition[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    private function map(string $path, mixed $value): array
    {
        if (! is_array($value)) {
            throw InvalidConfigValueException::forType($path, 'array with string keys', $value, self::class);
        }

        foreach ($value as $key => $_) {
            if (! is_string($key)) {
                throw InvalidConfigValueException::forType($path, 'array with string keys', $value, self::class);
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
