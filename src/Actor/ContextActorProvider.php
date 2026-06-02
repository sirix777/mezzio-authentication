<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Actor;

use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\SecurityActorProviderInterface;

final readonly class ContextActorProvider implements SecurityActorProviderInterface
{
    public function __construct(private AuthContextInterface $authContext, private ActorInterface $guestActor) {}

    public function getActor(): ActorInterface
    {
        return $this->authContext->actor() ?? $this->guestActor;
    }
}
