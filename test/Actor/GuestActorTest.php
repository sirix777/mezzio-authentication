<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Actor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Actor\GuestActor;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;

final class GuestActorTest extends TestCase
{
    #[Test]
    public function implementsActorInterface(): void
    {
        $actor = new GuestActor();

        self::assertInstanceOf(ActorInterface::class, $actor);
    }

    #[Test]
    public function returnsGuestRole(): void
    {
        $actor = new GuestActor();

        self::assertSame(['guest'], $actor->getRoles());
    }
}
