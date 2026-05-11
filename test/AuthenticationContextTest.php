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
        $context = new AuthenticationContext();

        self::assertTrue($context->guest());
        self::assertFalse($context->check());
        self::assertNull($context->token());
        self::assertNull($context->actor());
    }

    #[Test]
    public function constructorSetsTokenAndActor(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $actor = $this->createStub(ActorInterface::class);

        $context = new AuthenticationContext($token, $actor);

        self::assertTrue($context->check());
        self::assertFalse($context->guest());
        self::assertSame($token, $context->token());
        self::assertSame($actor, $context->actor());
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
        $context = new AuthenticationContext($token);

        self::assertTrue($context->check());
    }
}
