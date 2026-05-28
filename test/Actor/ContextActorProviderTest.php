<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Actor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Actor\ContextActorProvider;
use Sirix\Mezzio\Authentication\Actor\GuestActor;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\SecurityActorProviderInterface;

final class ContextActorProviderTest extends TestCase
{
    #[Test]
    public function implementsSecurityActorProviderInterface(): void
    {
        $contextActorProvider = new ContextActorProvider(
            $this->createStub(AuthContextInterface::class),
            new GuestActor(),
        );

        self::assertInstanceOf(SecurityActorProviderInterface::class, $contextActorProvider);
    }

    #[Test]
    public function returnsActorFromContext(): void
    {
        $actor = $this->createStub(ActorInterface::class);

        $context = $this->createMock(AuthContextInterface::class);
        $context
            ->expects($this->once())
            ->method('actor')
            ->willReturn($actor)
        ;

        $contextActorProvider = new ContextActorProvider($context, new GuestActor());

        self::assertSame($actor, $contextActorProvider->getActor());
    }

    #[Test]
    public function returnsGuestWhenContextHasNoActor(): void
    {
        $context = $this->createStub(AuthContextInterface::class);
        $context->method('actor')->willReturn(null);

        $guestActor = new GuestActor();
        $contextActorProvider = new ContextActorProvider($context, $guestActor);

        self::assertSame($guestActor, $contextActorProvider->getActor());
    }
}
