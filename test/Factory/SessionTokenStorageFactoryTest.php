<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use Mezzio\Session\SessionInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Authentication\Factory\SessionTokenStorageFactory;
use SirixTest\Mezzio\Authentication\Support\InMemorySession;

final class SessionTokenStorageFactoryTest extends TestCase
{
    #[Test]
    public function usesDefaultSessionPrefix(): void
    {
        $storage = (new SessionTokenStorageFactory())($this->createContainer([]));

        $session = new InMemorySession();
        $request = (new Psr17Factory())
            ->createServerRequest('GET', '/')
            ->withAttribute(SessionInterface::class, $session)
        ;

        $token = $storage->create(['id' => 1], null, $request);

        self::assertNotNull($session->get('_authentication.tokens.' . $token->getId()));
    }

    #[Test]
    public function readsSessionPrefixFromConfig(): void
    {
        $storage = (new SessionTokenStorageFactory())($this->createContainer([
            'authentication' => [
                'session' => [
                    'prefix' => '_custom.auth.',
                ],
            ],
        ]));

        $session = new InMemorySession();
        $request = (new Psr17Factory())
            ->createServerRequest('GET', '/')
            ->withAttribute(SessionInterface::class, $session)
        ;

        $token = $storage->create(['id' => 1], null, $request);

        self::assertNotNull($session->get('_custom.auth.' . $token->getId()));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createContainer(array $config): ContainerInterface
    {
        return new class($config) implements ContainerInterface {
            /**
             * @param array<string, mixed> $config
             */
            public function __construct(private readonly array $config) {}

            public function get(string $id): mixed
            {
                return match ($id) {
                    'config' => $this->config,
                    default => null,
                };
            }

            public function has(string $id): bool
            {
                return 'config' === $id;
            }
        };
    }
}
