<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Storage;

use Mezzio\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Token\AuthToken;

use function bin2hex;
use function is_array;
use function is_int;
use function random_bytes;
use function time;

final readonly class SessionTokenStorage implements TokenStorageInterface
{
    public function __construct(private string $storage = 'session', private string $prefix = '_authentication.tokens.') {}

    public function create(array $payload, ?int $expiresAt = null, ?ServerRequestInterface $request = null): TokenInterface
    {
        $session = $this->session($request);

        $token = new AuthToken(
            bin2hex(random_bytes(16)),
            $this->storage,
            $payload,
            $expiresAt,
        );

        $session->set($this->key($token->getId()), [
            'payload' => $payload,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    public function load(string $id, ?ServerRequestInterface $request = null): ?TokenInterface
    {
        $session = $this->session($request);

        $record = $session->get($this->key($id));
        if (! is_array($record)) {
            return null;
        }

        $expiresAt = $record['expires_at'] ?? null;
        if (is_int($expiresAt) && $expiresAt < time()) {
            $session->unset($this->key($id));

            return null;
        }

        return new AuthToken(
            $id,
            $this->storage,
            is_array($record['payload'] ?? null) ? $record['payload'] : [],
            is_int($expiresAt) ? $expiresAt : null,
        );
    }

    public function delete(TokenInterface $token, ?ServerRequestInterface $request = null): void
    {
        $this->session($request)->unset($this->key($token->getId()));
    }

    private function session(?ServerRequestInterface $request): SessionInterface
    {
        $session = $request?->getAttribute(SessionInterface::class)
            ?? $request?->getAttribute('session');

        if (! $session instanceof SessionInterface) {
            throw new StorageException(
                'Session not found in request. Ensure Mezzio\Session\SessionMiddleware is registered in your pipeline before authentication middleware.',
            );
        }

        return $session;
    }

    private function key(string $id): string
    {
        return $this->prefix . $id;
    }
}
