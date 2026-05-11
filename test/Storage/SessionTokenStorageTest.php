<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Storage;

use Mezzio\Session\SessionInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\Storage\StorageException;
use SirixTest\Mezzio\Authentication\Support\InMemorySession;

use function usleep;

final class SessionTokenStorageTest extends TestCase
{
    private InMemorySession $session;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->session = new InMemorySession();
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function implementsTokenStorageInterface(): void
    {
        $storage = new SessionTokenStorage();

        self::assertInstanceOf(TokenStorageInterface::class, $storage);
    }

    #[Test]
    public function createStoresTokenInSession(): void
    {
        $storage = new SessionTokenStorage();
        $request = $this->requestWithSession();

        $token = $storage->create(['userId' => 42], null, $request);

        self::assertInstanceOf(TokenInterface::class, $token);
        self::assertSame('session', $token->getStorage());
        self::assertSame(['userId' => 42], $token->getPayload());

        $sessionData = $this->session->get('_authentication.tokens.' . $token->getId());
        self::assertIsArray($sessionData);
        self::assertSame(['userId' => 42], $sessionData['payload']);
    }

    #[Test]
    public function loadReturnsTokenFromSession(): void
    {
        $storage = new SessionTokenStorage();
        $request = $this->requestWithSession();

        $token = $storage->create(['userId' => 42], null, $request);
        $loaded = $storage->load($token->getId(), $request);

        self::assertInstanceOf(TokenInterface::class, $loaded);
        self::assertSame($token->getId(), $loaded->getId());
        self::assertSame(['userId' => 42], $loaded->getPayload());
    }

    #[Test]
    public function loadReturnsNullForUnknownId(): void
    {
        $storage = new SessionTokenStorage();
        $request = $this->requestWithSession();

        self::assertNull($storage->load('nonexistent', $request));
    }

    #[Test]
    public function deleteRemovesTokenFromSession(): void
    {
        $storage = new SessionTokenStorage();
        $request = $this->requestWithSession();

        $token = $storage->create(['userId' => 42], null, $request);
        $storage->delete($token, $request);

        self::assertNull($storage->load($token->getId(), $request));
    }

    #[Test]
    public function throwsExceptionWhenSessionNotInRequest(): void
    {
        $storage = new SessionTokenStorage();
        $request = $this->factory->createServerRequest('GET', '/');

        $this->expectException(StorageException::class);
        $storage->load('some-id', $request);
    }

    #[Test]
    public function readsSessionFromLegacySessionAttribute(): void
    {
        $storage = new SessionTokenStorage();
        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withAttribute('session', $this->session)
        ;

        $token = $storage->create(['userId' => 42], null, $request);

        self::assertInstanceOf(TokenInterface::class, $storage->load($token->getId(), $request));
    }

    #[Test]
    public function loadReturnsNullForExpiredToken(): void
    {
        $storage = new SessionTokenStorage();
        $request = $this->requestWithSession();

        $token = $storage->create(['userId' => 42], 1, $request);

        usleep(2000); // Ensure we're past the expiry time
        $loaded = $storage->load($token->getId(), $request);

        self::assertNull($loaded);
    }

    private function requestWithSession(): ServerRequestInterface
    {
        return $this->factory
            ->createServerRequest('GET', '/')
            ->withAttribute(SessionInterface::class, $this->session)
        ;
    }
}
