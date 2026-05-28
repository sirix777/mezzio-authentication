<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\TokenAuthenticator;

final class TokenAuthenticatorTest extends TestCase
{
    #[Test]
    public function clearsContextWhenTokenIsNull(): void
    {
        $actorProvider = $this->createStub(AuthActorProviderInterface::class);

        $tokenAuthenticator = new TokenAuthenticator($actorProvider);
        $authContext = $tokenAuthenticator->authenticate(null);

        self::assertInstanceOf(AuthContextInterface::class, $authContext);
        self::assertTrue($authContext->guest());
    }

    #[Test]
    public function setsTokenAndActorOnContext(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $actor = $this->createStub(ActorInterface::class);

        $actorProvider = $this->createMock(AuthActorProviderInterface::class);
        $actorProvider
            ->expects($this->once())
            ->method('getActor')
            ->with($token)
            ->willReturn($actor)
        ;

        $tokenAuthenticator = new TokenAuthenticator($actorProvider);
        $authContext = $tokenAuthenticator->authenticate($token);

        self::assertTrue($authContext->check());
        self::assertSame($token, $authContext->token());
        self::assertSame($actor, $authContext->actor());
    }
}
