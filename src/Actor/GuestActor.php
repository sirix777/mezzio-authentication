<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Actor;

use Sirix\Mezzio\Authentication\Contract\ActorInterface;

final readonly class GuestActor implements ActorInterface
{
    public function getRoles(): array
    {
        return ['guest'];
    }
}
