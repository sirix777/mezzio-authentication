<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

interface AuthActorProviderInterface
{
    public function getActor(TokenInterface $token): ?ActorInterface;
}
