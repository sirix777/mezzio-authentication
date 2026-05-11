<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Storage;

use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Token\AuthToken;

use function bin2hex;
use function random_bytes;

final readonly class NullTokenStorage implements TokenStorageInterface
{
    public function __construct(private string $storage = 'null') {}

    public function create(array $payload, ?int $expiresAt = null, ?ServerRequestInterface $request = null): TokenInterface
    {
        return new AuthToken(
            bin2hex(random_bytes(16)),
            $this->storage,
            $payload,
            $expiresAt,
        );
    }

    public function load(string $id, ?ServerRequestInterface $request = null): ?TokenInterface
    {
        return null;
    }

    public function delete(TokenInterface $token, ?ServerRequestInterface $request = null): void {}
}
