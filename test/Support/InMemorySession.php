<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Support;

use Mezzio\Session\SessionInterface;

final class InMemorySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    private bool $regenerated = false;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function get(string $name, $default = null): mixed
    {
        return $this->data[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function set(string $name, $value): void
    {
        $this->data[$name] = $value;
    }

    public function unset(string $name): void
    {
        unset($this->data[$name]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function hasChanged(): bool
    {
        return $this->regenerated;
    }

    public function regenerate(): SessionInterface
    {
        $clone = clone $this;
        $clone->regenerated = true;

        return $clone;
    }

    public function isRegenerated(): bool
    {
        return $this->regenerated;
    }
}
