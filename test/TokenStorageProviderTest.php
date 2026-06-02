<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\TokenStorageProvider;

final class TokenStorageProviderTest extends TestCase
{
    #[Test]
    public function returnsStorageByName(): void
    {
        $storage = $this->createStub(TokenStorageInterface::class);
        $tokenStorageProvider = new TokenStorageProvider('default', ['default' => $storage]);

        self::assertSame($storage, $tokenStorageProvider->getStorage('default'));
    }

    #[Test]
    public function getDefaultStorageReturnsConfiguredDefault(): void
    {
        $storage = $this->createStub(TokenStorageInterface::class);
        $tokenStorageProvider = new TokenStorageProvider('session', [
            'null' => $this->createStub(TokenStorageInterface::class),
            'session' => $storage,
        ]);

        self::assertSame($storage, $tokenStorageProvider->getDefaultStorage());
    }

    #[Test]
    public function throwsExceptionForUnknownStorage(): void
    {
        $tokenStorageProvider = new TokenStorageProvider('null', [
            'null' => $this->createStub(TokenStorageInterface::class),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $tokenStorageProvider->getStorage('unknown');
    }
}
