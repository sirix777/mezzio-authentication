<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Actor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Actor\TokenActor;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;

final class TokenActorTest extends TestCase
{
    #[Test]
    public function implementsActorInterface(): void
    {
        $actor = new TokenActor([]);

        self::assertInstanceOf(ActorInterface::class, $actor);
    }

    #[Test]
    public function returnsRoles(): void
    {
        $actor = new TokenActor(['admin', 'editor']);

        self::assertSame(['admin', 'editor'], $actor->getRoles());
    }

    #[Test]
    public function returnsEmptyRolesByDefault(): void
    {
        $actor = new TokenActor([]);

        self::assertSame([], $actor->getRoles());
    }
}
