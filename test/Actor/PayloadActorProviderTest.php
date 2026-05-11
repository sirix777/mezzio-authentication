<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Actor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Actor\PayloadActorProvider;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

final class PayloadActorProviderTest extends TestCase
{
    #[Test]
    public function extractsRolesFromList(): void
    {
        $token = $this->createToken(['roles' => ['admin', 'editor']]);
        $provider = new PayloadActorProvider();

        $actor = $provider->getActor($token);

        self::assertInstanceOf(ActorInterface::class, $actor);
        self::assertSame(['admin', 'editor'], $actor->getRoles());
    }

    #[Test]
    public function extractsRolesWithCustomKey(): void
    {
        $token = $this->createToken(['permissions' => ['read', 'write']]);
        $provider = new PayloadActorProvider('permissions');

        $actor = $provider->getActor($token);

        self::assertSame(['read', 'write'], $actor->getRoles());
    }

    #[Test]
    public function extractsSingleRole(): void
    {
        $token = $this->createToken(['role' => 'admin']);
        $provider = new PayloadActorProvider();

        $actor = $provider->getActor($token);

        self::assertSame(['admin'], $actor->getRoles());
    }

    #[Test]
    public function returnsEmptyRolesWhenNonePresent(): void
    {
        $token = $this->createToken([]);
        $provider = new PayloadActorProvider();

        $actor = $provider->getActor($token);

        self::assertSame([], $actor->getRoles());
    }

    #[Test]
    public function filtersInvalidRoleValues(): void
    {
        $token = $this->createToken(['roles' => ['admin', 123, '', null]]);
        $provider = new PayloadActorProvider();

        $actor = $provider->getActor($token);

        self::assertSame(['admin'], $actor->getRoles());
    }

    #[Test]
    public function listRolesTakePrecedenceOverSingleRole(): void
    {
        $token = $this->createToken([
            'roles' => ['admin'],
            'role' => 'editor',
        ]);
        $provider = new PayloadActorProvider();

        $actor = $provider->getActor($token);

        self::assertSame(['admin'], $actor->getRoles());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createToken(array $payload): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getPayload')->willReturn($payload);

        return $token;
    }
}
