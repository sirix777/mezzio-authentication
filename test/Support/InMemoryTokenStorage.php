<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Support;

use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Token\AuthToken;

/**
 * @internal
 */
final class InMemoryTokenStorage implements TokenStorageInterface
{
    /** @var list<string> */
    public array $operations = [];

    /** @var array<string, TokenInterface> */
    private array $tokens = [];

    private int $sequence = 0;

    public function __construct(private readonly string $name) {}

    public function create(array $payload, ?int $expiresAt = null, ?ServerRequestInterface $serverRequest = null): TokenInterface
    {
        $this->operations[]                = 'create';
        $authToken                         = new AuthToken($this->name . '-' . ++$this->sequence, $this->name, $payload, $expiresAt);
        $this->tokens[$authToken->getId()] = $authToken;

        return $authToken;
    }

    public function load(string $id, ?ServerRequestInterface $serverRequest = null): ?TokenInterface
    {
        $this->operations[] = 'load';

        return $this->tokens[$id] ?? null;
    }

    public function delete(TokenInterface $token, ?ServerRequestInterface $serverRequest = null): void
    {
        $this->operations[] = 'delete';
        unset($this->tokens[$token->getId()]);
    }
}
