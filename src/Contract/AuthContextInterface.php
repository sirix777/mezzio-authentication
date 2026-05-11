<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

interface AuthContextInterface
{
    public function token(): ?TokenInterface;

    public function actor(): ?ActorInterface;

    public function check(): bool;

    public function guest(): bool;
}
