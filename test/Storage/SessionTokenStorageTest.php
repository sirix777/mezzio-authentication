<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Storage;

use Mezzio\Session\SessionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Exception\StorageException;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use SirixTest\Mezzio\Authentication\Support\InMemorySession;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

use function usleep;

final class SessionTokenStorageTest extends TestCase
{
    private InMemorySession $inMemorySession;
    private Psr7Factory $psr7Factory;

    protected function setUp(): void
    {
        $this->inMemorySession = new InMemorySession();
        $this->psr7Factory = new Psr7Factory();
    }

    #[Test]
    public function implementsTokenStorageInterface(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();

        self::assertInstanceOf(TokenStorageInterface::class, $sessionTokenStorage);
    }

    #[Test]
    public function createStoresTokenInSession(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest = $this->requestWithSession();

        $token = $sessionTokenStorage->create(['userId' => 42], null, $serverRequest);

        self::assertInstanceOf(TokenInterface::class, $token);
        self::assertSame('session', $token->getStorage());
        self::assertSame(['userId' => 42], $token->getPayload());

        $sessionData = $this->inMemorySession->get('_authentication.tokens.' . $token->getId());
        self::assertIsArray($sessionData);
        self::assertSame(['userId' => 42], $sessionData['payload']);
    }

    #[Test]
    public function loadReturnsTokenFromSession(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest = $this->requestWithSession();

        $token = $sessionTokenStorage->create(['userId' => 42], null, $serverRequest);
        $loaded = $sessionTokenStorage->load($token->getId(), $serverRequest);

        self::assertInstanceOf(TokenInterface::class, $loaded);
        self::assertSame($token->getId(), $loaded->getId());
        self::assertSame(['userId' => 42], $loaded->getPayload());
    }

    #[Test]
    public function loadReturnsNullForUnknownId(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest = $this->requestWithSession();

        self::assertNull($sessionTokenStorage->load('nonexistent', $serverRequest));
    }

    #[Test]
    public function deleteRemovesTokenFromSession(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest = $this->requestWithSession();

        $token = $sessionTokenStorage->create(['userId' => 42], null, $serverRequest);
        $sessionTokenStorage->delete($token, $serverRequest);

        self::assertNull($sessionTokenStorage->load($token->getId(), $serverRequest));
    }

    #[Test]
    public function throwsExceptionWhenSessionNotInRequest(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest = $this->psr7Factory->createServerRequest('GET', '/');

        $this->expectException(StorageException::class);
        $sessionTokenStorage->load('some-id', $serverRequest);
    }

    #[Test]
    public function readsSessionFromLegacySessionAttribute(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute('session', $this->inMemorySession)
        ;

        $token = $sessionTokenStorage->create(['userId' => 42], null, $serverRequest);

        self::assertInstanceOf(TokenInterface::class, $sessionTokenStorage->load($token->getId(), $serverRequest));
    }

    #[Test]
    public function loadReturnsNullForExpiredToken(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest = $this->requestWithSession();

        $token = $sessionTokenStorage->create(['userId' => 42], 1, $serverRequest);

        usleep(2000); // Ensure we're past the expiry time
        $loaded = $sessionTokenStorage->load($token->getId(), $serverRequest);

        self::assertNull($loaded);
    }

    private function requestWithSession(): ServerRequestInterface
    {
        return $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute(SessionInterface::class, $this->inMemorySession)
        ;
    }
}
