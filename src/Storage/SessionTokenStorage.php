<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Storage;

use Mezzio\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Exception\StorageException;
use Sirix\Mezzio\Authentication\Token\AuthToken;

use function bin2hex;
use function is_array;
use function is_int;
use function random_bytes;
use function time;

final readonly class SessionTokenStorage implements TokenStorageInterface
{
    public function __construct(private string $storage = 'session', private string $prefix = '_authentication.tokens.') {}

    public function create(array $payload, ?int $expiresAt = null, ?ServerRequestInterface $serverRequest = null): TokenInterface
    {
        $session = $this->session($serverRequest);

        $authToken = new AuthToken(
            bin2hex(random_bytes(16)),
            $this->storage,
            $payload,
            $expiresAt,
        );

        $session->set($this->key($authToken->getId()), [
            'payload'    => $payload,
            'expires_at' => $expiresAt,
        ]);

        return $authToken;
    }

    public function load(string $id, ?ServerRequestInterface $serverRequest = null): ?TokenInterface
    {
        $session = $this->session($serverRequest);

        $key    = $this->key($id);
        $record = $session->get($key);
        if (! is_array($record)) {
            if ($session->has($key)) {
                $session->unset($key);
            }

            return null;
        }

        $expiresAt = $record['expires_at'] ?? null;
        $payload   = $record['payload'] ?? null;
        if (! is_array($payload) || (null !== $expiresAt && ! is_int($expiresAt))) {
            $session->unset($this->key($id));

            return null;
        }

        if (is_int($expiresAt) && $expiresAt <= time()) {
            $session->unset($this->key($id));

            return null;
        }

        return new AuthToken(
            $id,
            $this->storage,
            $payload,
            is_int($expiresAt) ? $expiresAt : null,
        );
    }

    public function delete(TokenInterface $token, ?ServerRequestInterface $serverRequest = null): void
    {
        $this->session($serverRequest)->unset($this->key($token->getId()));
    }

    private function session(?ServerRequestInterface $serverRequest): SessionInterface
    {
        $session = $serverRequest?->getAttribute(SessionInterface::class)
            ?? $serverRequest?->getAttribute('session');

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
