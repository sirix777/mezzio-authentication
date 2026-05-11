<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Actor;

use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

final class NullActorProvider implements AuthActorProviderInterface
{
    public function getActor(TokenInterface $token): ?ActorInterface
    {
        return null;
    }
}
