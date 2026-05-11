<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

interface TokenStorageProviderInterface
{
    public function getStorage(string $name): TokenStorageInterface;

    public function getDefaultStorage(): TokenStorageInterface;
}
