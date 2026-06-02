<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Storage;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;

final class NullTokenStorageTest extends TestCase
{
    private NullTokenStorage $nullTokenStorage;

    protected function setUp(): void
    {
        $this->nullTokenStorage = new NullTokenStorage();
    }

    #[Test]
    public function implementsTokenStorageInterface(): void
    {
        self::assertInstanceOf(TokenStorageInterface::class, $this->nullTokenStorage);
    }

    #[Test]
    public function createReturnsToken(): void
    {
        $token = $this->nullTokenStorage->create(['userId' => 1]);

        self::assertInstanceOf(TokenInterface::class, $token);
        self::assertNotEmpty($token->getId());
        self::assertSame('null', $token->getStorage());
        self::assertSame(['userId' => 1], $token->getPayload());
        self::assertNull($token->getExpiresAt());
    }

    #[Test]
    public function createWithCustomStorageName(): void
    {
        $nullTokenStorage = new NullTokenStorage('custom');
        $token = $nullTokenStorage->create([]);

        self::assertSame('custom', $token->getStorage());
    }

    #[Test]
    public function loadAlwaysReturnsNull(): void
    {
        self::assertNull($this->nullTokenStorage->load('any-id'));
    }

    #[Test]
    public function deleteDoesNothing(): void
    {
        $token = $this->nullTokenStorage->create([]);
        $this->nullTokenStorage->delete($token);

        self::assertNull($this->nullTokenStorage->load($token->getId()));
    }
}
