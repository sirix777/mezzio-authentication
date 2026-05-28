<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use Mezzio\Session\SessionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Factory\SessionTokenStorageFactory;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;
use SirixTest\Mezzio\Authentication\Support\InMemorySession;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class SessionTokenStorageFactoryTest extends TestCase
{
    #[Test]
    public function usesDefaultSessionPrefix(): void
    {
        $storage = (new SessionTokenStorageFactory())(new ArrayContainer([
            'config' => [],
        ]));

        $inMemorySession = new InMemorySession();
        $serverRequest = (new Psr7Factory())
            ->createServerRequest('GET', '/')
            ->withAttribute(SessionInterface::class, $inMemorySession)
        ;

        $token = $storage->create(['id' => 1], null, $serverRequest);

        self::assertNotNull($inMemorySession->get('_authentication.tokens.' . $token->getId()));
    }

    #[Test]
    public function readsSessionPrefixFromConfig(): void
    {
        $storage = (new SessionTokenStorageFactory())(new ArrayContainer([
            'config' => [
                'authentication' => [
                    'session' => [
                        'prefix' => '_custom.auth.',
                    ],
                ],
            ],
        ]));

        $inMemorySession = new InMemorySession();
        $serverRequest = (new Psr7Factory())
            ->createServerRequest('GET', '/')
            ->withAttribute(SessionInterface::class, $inMemorySession)
        ;

        $token = $storage->create(['id' => 1], null, $serverRequest);

        self::assertNotNull($inMemorySession->get('_custom.auth.' . $token->getId()));
    }
}
