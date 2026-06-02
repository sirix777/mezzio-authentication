<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

final class AuthenticationContextTest extends TestCase
{
    #[Test]
    public function implementsAuthContextInterface(): void
    {
        self::assertInstanceOf(AuthContextInterface::class, new AuthenticationContext());
    }

    #[Test]
    public function startsAsGuest(): void
    {
        $authenticationContext = new AuthenticationContext();

        self::assertTrue($authenticationContext->guest());
        self::assertFalse($authenticationContext->check());
        self::assertNull($authenticationContext->token());
        self::assertNull($authenticationContext->actor());
    }

    #[Test]
    public function constructorSetsTokenAndActor(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $actor = $this->createStub(ActorInterface::class);

        $authenticationContext = new AuthenticationContext($token, $actor);

        self::assertTrue($authenticationContext->check());
        self::assertFalse($authenticationContext->guest());
        self::assertSame($token, $authenticationContext->token());
        self::assertSame($actor, $authenticationContext->actor());
    }

    #[Test]
    public function guestReturnsTrueWhenNoToken(): void
    {
        self::assertTrue((new AuthenticationContext())->guest());
    }

    #[Test]
    public function checkReturnsTrueWhenTokenSet(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $authenticationContext = new AuthenticationContext($token);

        self::assertTrue($authenticationContext->check());
    }
}
