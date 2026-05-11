<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

interface SecurityActorProviderInterface
{
    public function getActor(): ActorInterface;
}
