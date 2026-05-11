<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

use InvalidArgumentException;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;

final readonly class TokenStorageProvider implements TokenStorageProviderInterface
{
    /**
     * @param array<string, TokenStorageInterface> $storages
     */
    public function __construct(private string $defaultStorage, private array $storages) {}

    public function getStorage(string $name): TokenStorageInterface
    {
        return $this->storages[$name]
            ?? throw new InvalidArgumentException(
                "Authentication storage '{$name}' is not registered.",
            );
    }

    public function getDefaultStorage(): TokenStorageInterface
    {
        return $this->getStorage($this->defaultStorage);
    }
}
