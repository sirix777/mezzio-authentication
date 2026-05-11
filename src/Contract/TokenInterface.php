<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

interface TokenInterface
{
    public function getId(): string;

    public function getStorage(): string;

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array;

    public function getExpiresAt(): ?int;
}
