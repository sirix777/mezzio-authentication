<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

use Psr\Http\Message\ServerRequestInterface;

interface TokenStorageInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload, ?int $expiresAt = null, ?ServerRequestInterface $serverRequest = null): TokenInterface;

    public function load(string $id, ?ServerRequestInterface $serverRequest = null): ?TokenInterface;

    public function delete(TokenInterface $token, ?ServerRequestInterface $serverRequest = null): void;
}
