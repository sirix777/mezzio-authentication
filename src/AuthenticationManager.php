<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;

final readonly class AuthenticationManager implements AuthManagerInterface
{
    public function __construct(private TokenStorageProviderInterface $storageProvider, private TokenTransportInterface $transport) {}

    public function login(array $payload, ?string $storage = null, ?int $expiresAt = null): TokenInterface
    {
        return null === $storage
            ? $this->storageProvider
                ->getDefaultStorage()
                ->create($payload, $expiresAt)
            : $this->storageProvider
                ->getStorage($storage)
                ->create($payload, $expiresAt)
        ;
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $this->token($request);
        if ($token instanceof TokenInterface) {
            $this->storageProvider
                ->getStorage($token->getStorage())
                ->delete($token, $request)
            ;
        }

        return $this->transport->detach($response);
    }

    public function token(ServerRequestInterface $request): ?TokenInterface
    {
        return $this->context($request)->token();
    }

    public function actor(ServerRequestInterface $request): ?ActorInterface
    {
        return $this->context($request)->actor();
    }

    public function check(ServerRequestInterface $request): bool
    {
        return $this->context($request)->check();
    }

    public function guest(ServerRequestInterface $request): bool
    {
        return $this->context($request)->guest();
    }

    public function context(ServerRequestInterface $request): AuthContextInterface
    {
        $context = $request->getAttribute(AuthenticationAttributes::Context->value);

        return $context instanceof AuthContextInterface
            ? $context
            : new AuthenticationContext();
    }
}
