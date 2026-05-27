<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Factory\TokenStorageProviderFactory;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;
use stdClass;

final class TokenStorageProviderFactoryTest extends TestCase
{
    #[Test]
    public function fallsBackToNullStorageWhenSessionStorageIsNotRegistered(): void
    {
        $factory = new TokenStorageProviderFactory();
        $container = new ArrayContainer([
            'config' => ['authentication' => ['default_storage' => 'session']],
            NullTokenStorage::class => new NullTokenStorage(),
        ]);

        $provider = $factory($container);

        self::assertInstanceOf(NullTokenStorage::class, $provider->getDefaultStorage());
    }

    #[Test]
    public function usesSessionStorageWhenItIsRegistered(): void
    {
        $factory = new TokenStorageProviderFactory();
        $sessionStorage = new SessionTokenStorage();
        $container = new ArrayContainer([
            'config' => ['authentication' => ['default_storage' => 'session']],
            NullTokenStorage::class => new NullTokenStorage(),
            SessionTokenStorage::class => $sessionStorage,
        ]);

        $provider = $factory($container);

        self::assertSame($sessionStorage, $provider->getDefaultStorage());
    }

    #[Test]
    public function registersStoragesFromConfigurationMapping(): void
    {
        $factory = new TokenStorageProviderFactory();
        $customStorage = new class implements TokenStorageInterface {
            public function create(array $payload, ?int $expiresAt = null, ?ServerRequestInterface $request = null): TokenInterface
            {
                throw new LogicException('Not needed for this test.');
            }

            public function load(string $id, ?ServerRequestInterface $request = null): ?TokenInterface
            {
                return null;
            }

            public function delete(TokenInterface $token, ?ServerRequestInterface $request = null): void {}
        };

        $container = new ArrayContainer([
            'config' => [
                'authentication' => [
                    'default_storage' => 'redis',
                    'storages' => [
                        'redis' => 'app.storage.redis',
                    ],
                ],
            ],
            NullTokenStorage::class => new NullTokenStorage(),
            'app.storage.redis' => $customStorage,
        ]);

        $provider = $factory($container);

        self::assertSame($customStorage, $provider->getDefaultStorage());
        self::assertSame($customStorage, $provider->getStorage('redis'));
    }

    #[Test]
    public function throwsForMappedServiceWithInvalidType(): void
    {
        $factory = new TokenStorageProviderFactory();
        $container = new ArrayContainer([
            'config' => [
                'authentication' => [
                    'storages' => [
                        'broken' => 'app.storage.broken',
                    ],
                ],
            ],
            NullTokenStorage::class => new NullTokenStorage(),
            'app.storage.broken' => new stdClass(),
        ]);

        $this->expectException(InvalidArgumentException::class);

        $factory($container);
    }
}
