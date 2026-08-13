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
    public function login(
        ServerRequestInterface $serverRequest,
        ResponseInterface $response,
        array $payload,
        ?int $expiresAt = null,
    ): ResponseInterface;

    public function logout(ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface;

    public function token(ServerRequestInterface $serverRequest): ?TokenInterface;

    public function actor(ServerRequestInterface $serverRequest): ?ActorInterface;

    public function check(ServerRequestInterface $serverRequest): bool;

    public function guest(ServerRequestInterface $serverRequest): bool;

    public function context(ServerRequestInterface $serverRequest): AuthContextInterface;
}
