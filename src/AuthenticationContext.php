<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

final readonly class AuthenticationContext implements AuthContextInterface
{
    public function __construct(private ?TokenInterface $token = null, private ?ActorInterface $actor = null) {}

    public function token(): ?TokenInterface
    {
        return $this->token;
    }

    public function actor(): ?ActorInterface
    {
        return $this->actor;
    }

    public function check(): bool
    {
        return $this->token instanceof TokenInterface;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }
}
