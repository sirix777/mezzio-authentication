<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

final readonly class AuthenticationContext implements AuthContextInterface
{
    private ?TokenInterface $token;
    private ?ActorInterface $actor;

    public function __construct(?TokenInterface $token = null, ?ActorInterface $actor = null)
    {
        $this->token = $token instanceof TokenInterface && $actor instanceof ActorInterface
            ? $token
            : null;
        $this->actor = $actor instanceof ActorInterface && $token instanceof TokenInterface
            ? $actor
            : null;
    }

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
