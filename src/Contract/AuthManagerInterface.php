<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface AuthManagerInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function login(array $payload, ?string $storage = null, ?int $expiresAt = null): TokenInterface;

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface;

    public function token(ServerRequestInterface $request): ?TokenInterface;

    public function actor(ServerRequestInterface $request): ?ActorInterface;

    public function check(ServerRequestInterface $request): bool;

    public function guest(ServerRequestInterface $request): bool;

    public function context(ServerRequestInterface $request): AuthContextInterface;
}
