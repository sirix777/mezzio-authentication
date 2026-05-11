<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Actor;

use Sirix\Mezzio\Authentication\Contract\ActorInterface;

final readonly class TokenActor implements ActorInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(private array $roles) {}

    public function getRoles(): array
    {
        return $this->roles;
    }
}
