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
        $tokenActor = new TokenActor([]);

        self::assertInstanceOf(ActorInterface::class, $tokenActor);
    }

    #[Test]
    public function returnsRoles(): void
    {
        $tokenActor = new TokenActor(['admin', 'editor']);

        self::assertSame(['admin', 'editor'], $tokenActor->getRoles());
    }

    #[Test]
    public function returnsEmptyRolesByDefault(): void
    {
        $tokenActor = new TokenActor([]);

        self::assertSame([], $tokenActor->getRoles());
    }
}
