<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;

final readonly class AuthenticationManager implements AuthManagerInterface
{
    public function __construct(
        private TokenStorageProviderInterface $tokenStorageProvider,
        private TokenTransportInterface $tokenTransport,
        private ?string $storage = null,
    ) {}

    public function login(
        ServerRequestInterface $serverRequest,
        ResponseInterface $response,
        array $payload,
        ?int $expiresAt = null,
    ): ResponseInterface {
        $token = $this->storage()->create($payload, $expiresAt, $serverRequest);

        return $this->tokenTransport->attach($response, $token);
    }

    public function logout(ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface
    {
        $token = $this->token($serverRequest);
        if ($token instanceof TokenInterface) {
            $this->storage()->delete($token, $serverRequest);
        }

        return $this->tokenTransport->detach($response);
    }

    public function token(ServerRequestInterface $serverRequest): ?TokenInterface
    {
        return $this->context($serverRequest)->token();
    }

    public function actor(ServerRequestInterface $serverRequest): ?ActorInterface
    {
        return $this->context($serverRequest)->actor();
    }

    public function check(ServerRequestInterface $serverRequest): bool
    {
        return $this->context($serverRequest)->check();
    }

    public function guest(ServerRequestInterface $serverRequest): bool
    {
        return $this->context($serverRequest)->guest();
    }

    public function context(ServerRequestInterface $serverRequest): AuthContextInterface
    {
        $context = $serverRequest->getAttribute(AuthenticationAttributes::Context->value);

        return $context instanceof AuthContextInterface
            ? $context
            : new AuthenticationContext();
    }

    private function storage(): TokenStorageInterface
    {
        return null === $this->storage
            ? $this->tokenStorageProvider->getDefaultStorage()
            : $this->tokenStorageProvider->getStorage($this->storage);
    }
}
