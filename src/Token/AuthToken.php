<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Token;

use Sirix\Mezzio\Authentication\Contract\TokenInterface;

final readonly class AuthToken implements TokenInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private string $id, private string $storage, private array $payload, private ?int $expiresAt = null) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getStorage(): string
    {
        return $this->storage;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }
}
