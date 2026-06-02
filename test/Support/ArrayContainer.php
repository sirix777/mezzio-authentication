<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Support;

use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_key_exists;

/**
 * @internal
 */
final readonly class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(private array $services = []) {}

    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new RuntimeException("Service '{$id}' not found.");
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
