<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
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
    public function throwsWhenDefaultSessionStorageIsNotRegistered(): void
    {
        $tokenStorageProviderFactory = new TokenStorageProviderFactory();
        $arrayContainer = new ArrayContainer([
            'config' => ['authentication' => ['default_storage' => 'session']],
            NullTokenStorage::class => new NullTokenStorage(),
        ]);

        $this->expectException(MissingContainerServiceException::class);

        $tokenStorageProviderFactory($arrayContainer);
    }

    #[Test]
    public function usesSessionStorageWhenItIsRegistered(): void
    {
        $tokenStorageProviderFactory = new TokenStorageProviderFactory();
        $sessionTokenStorage = new SessionTokenStorage();
        $arrayContainer = new ArrayContainer([
            'config' => ['authentication' => ['default_storage' => 'session']],
            NullTokenStorage::class => new NullTokenStorage(),
            SessionTokenStorage::class => $sessionTokenStorage,
        ]);

        $tokenStorageProvider = $tokenStorageProviderFactory($arrayContainer);

        self::assertSame($sessionTokenStorage, $tokenStorageProvider->getDefaultStorage());
    }

    #[Test]
    public function registersStoragesFromConfigurationMapping(): void
    {
        $tokenStorageProviderFactory = new TokenStorageProviderFactory();
        $customStorage = new class implements TokenStorageInterface {
            public function create(array $payload, ?int $expiresAt = null, ?ServerRequestInterface $serverRequest = null): TokenInterface
            {
                throw new LogicException('Not needed for this test.');
            }

            public function load(string $id, ?ServerRequestInterface $serverRequest = null): ?TokenInterface
            {
                return null;
            }

            public function delete(TokenInterface $token, ?ServerRequestInterface $serverRequest = null): void {}
        };

        $arrayContainer = new ArrayContainer([
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

        $tokenStorageProvider = $tokenStorageProviderFactory($arrayContainer);

        self::assertSame($customStorage, $tokenStorageProvider->getDefaultStorage());
        self::assertSame($customStorage, $tokenStorageProvider->getStorage('redis'));
    }

    #[Test]
    public function throwsForMappedServiceWithInvalidType(): void
    {
        $tokenStorageProviderFactory = new TokenStorageProviderFactory();
        $arrayContainer = new ArrayContainer([
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

        $this->expectException(InvalidContainerServiceException::class);

        $tokenStorageProviderFactory($arrayContainer);
    }

    #[Test]
    public function throwsForMappedServiceThatIsNotRegistered(): void
    {
        $tokenStorageProviderFactory = new TokenStorageProviderFactory();
        $arrayContainer = new ArrayContainer([
            'config' => [
                'authentication' => [
                    'storages' => [
                        'redis' => 'app.storage.redis',
                    ],
                ],
            ],
            NullTokenStorage::class => new NullTokenStorage(),
        ]);

        $this->expectException(MissingContainerServiceException::class);

        $tokenStorageProviderFactory($arrayContainer);
    }
}
