<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

final readonly class TokenAuthenticator implements AuthenticatorInterface
{
    public function __construct(private AuthActorProviderInterface $actorProvider) {}

    public function authenticate(?TokenInterface $token): AuthContextInterface
    {
        if (! $token instanceof TokenInterface) {
            return new AuthenticationContext();
        }

        return new AuthenticationContext(
            $token,
            $this->actorProvider->getActor($token),
        );
    }
}
