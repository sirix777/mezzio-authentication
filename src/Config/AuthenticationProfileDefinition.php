<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Config;

/**
 * @internal
 */
final readonly class AuthenticationProfileDefinition
{
    /**
     * @param array<string, mixed> $transportOptions
     */
    public function __construct(
        private string $name,
        private string $transport,
        private string $storage,
        private array $transportOptions,
        private bool $hasTransportOptions,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function transport(): string
    {
        return $this->transport;
    }

    public function storage(): string
    {
        return $this->storage;
    }

    /**
     * @return array<string, mixed>
     */
    public function transportOptions(): array
    {
        return $this->transportOptions;
    }

    public function hasTransportOptions(): bool
    {
        return $this->hasTransportOptions;
    }

    public function path(): string
    {
        return 'authentication.profiles.' . $this->name;
    }
}
