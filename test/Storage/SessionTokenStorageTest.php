<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Storage;

use Mezzio\Session\LazySession;
use Mezzio\Session\Session;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionPersistenceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Exception\StorageException;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\Token\AuthToken;
use SirixTest\Mezzio\Authentication\Support\InMemorySession;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

use function time;
use function usleep;

final class SessionTokenStorageTest extends TestCase
{
    private InMemorySession $inMemorySession;
    private Psr7Factory $psr7Factory;

    protected function setUp(): void
    {
        $this->inMemorySession = new InMemorySession();
        $this->psr7Factory     = new Psr7Factory();
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
        $serverRequest       = $this->requestWithSession();

        $token = $sessionTokenStorage->create([
            'userId' => 42,
        ], null, $serverRequest);

        self::assertInstanceOf(TokenInterface::class, $token);
        self::assertSame('session', $token->getStorage());
        self::assertSame([
            'userId' => 42,
        ], $token->getPayload());

        $sessionData = $this->inMemorySession->get('_authentication.tokens.' . $token->getId());
        self::assertIsArray($sessionData);
        self::assertSame([
            'userId' => 42,
        ], $sessionData['payload']);
    }

    #[Test]
    public function loadReturnsTokenFromSession(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest       = $this->requestWithSession();

        $token = $sessionTokenStorage->create([
            'userId' => 42,
        ], null, $serverRequest);
        $loaded = $sessionTokenStorage->load($token->getId(), $serverRequest);

        self::assertInstanceOf(TokenInterface::class, $loaded);
        self::assertSame($token->getId(), $loaded->getId());
        self::assertSame([
            'userId' => 42,
        ], $loaded->getPayload());
    }

    #[Test]
    public function loadReturnsNullForUnknownId(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest       = $this->requestWithSession();

        self::assertNull($sessionTokenStorage->load('nonexistent', $serverRequest));
    }

    #[Test]
    public function deleteRemovesTokenFromSession(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest       = $this->requestWithSession();

        $token = $sessionTokenStorage->create([
            'userId' => 42,
        ], null, $serverRequest);
        $sessionTokenStorage->delete($token, $serverRequest);

        self::assertNull($sessionTokenStorage->load($token->getId(), $serverRequest));
    }

    #[Test]
    public function throwsExceptionWhenSessionNotInRequest(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest       = $this->psr7Factory->createServerRequest('GET', '/');

        $this->expectException(StorageException::class);
        $sessionTokenStorage->load('some-id', $serverRequest);
    }

    #[Test]
    public function readsSessionFromLegacySessionAttribute(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest       = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute('session', $this->inMemorySession)
        ;

        $token = $sessionTokenStorage->create([
            'userId' => 42,
        ], null, $serverRequest);

        self::assertInstanceOf(TokenInterface::class, $sessionTokenStorage->load($token->getId(), $serverRequest));
    }

    #[Test]
    public function loadReturnsNullForExpiredToken(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest       = $this->requestWithSession();

        $token = $sessionTokenStorage->create([
            'userId' => 42,
        ], 1, $serverRequest);

        usleep(2000); // Ensure we're past the expiry time
        $loaded = $sessionTokenStorage->load($token->getId(), $serverRequest);

        self::assertNull($loaded);
    }

    #[Test]
    public function loadTreatsCurrentExpiryTimeAsExpired(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest       = $this->requestWithSession();

        $token = $sessionTokenStorage->create([
            'userId' => 42,
        ], time(), $serverRequest);

        self::assertNull($sessionTokenStorage->load($token->getId(), $serverRequest));
    }

    #[Test]
    public function loadDiscardsCorruptedSessionRecord(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest       = $this->requestWithSession();
        $tokenId             = 'corrupt-token';

        $this->inMemorySession->set('_authentication.tokens.' . $tokenId, [
            'payload'    => 'not-an-array',
            'expires_at' => 'never',
        ]);

        self::assertNull($sessionTokenStorage->load($tokenId, $serverRequest));
        self::assertNull($this->inMemorySession->get('_authentication.tokens.' . $tokenId));
    }

    #[Test]
    public function loadDiscardsNonArraySessionRecord(): void
    {
        $sessionTokenStorage = new SessionTokenStorage();
        $serverRequest       = $this->requestWithSession();
        $tokenId             = 'corrupt-token';

        $this->inMemorySession->set('_authentication.tokens.' . $tokenId, 'not-an-array');

        self::assertNull($sessionTokenStorage->load($tokenId, $serverRequest));
        self::assertFalse($this->inMemorySession->has('_authentication.tokens.' . $tokenId));
    }

    #[Test]
    public function createRegeneratesTheSessionAndKeepsTheTokenRecord(): void
    {
        [$serverRequest, $lazySession] = $this->lazySessionRequest();
        $sessionTokenStorage           = new SessionTokenStorage();

        $token = $sessionTokenStorage->create([
            'user_id' => 1,
        ], null, $serverRequest);

        self::assertTrue($lazySession->isRegenerated());
        self::assertSame([
            'user_id' => 1,
        ], $sessionTokenStorage->load($token->getId(), $serverRequest)?->getPayload());
    }

    #[Test]
    public function deleteRegeneratesTheSessionAndRemovesTheTokenRecord(): void
    {
        $sessionTokenStorage           = new SessionTokenStorage();
        [$serverRequest]               = $this->lazySessionRequest();
        $token                         = $sessionTokenStorage->create([], null, $serverRequest);
        [$logoutRequest, $lazySession] = $this->lazySessionRequest($serverRequest->getAttribute(SessionInterface::class)->toArray());

        $sessionTokenStorage->delete($token, $logoutRequest);

        self::assertTrue($lazySession->isRegenerated());
        self::assertNull($sessionTokenStorage->load($token->getId(), $logoutRequest));
    }

    #[Test]
    public function deleteRegeneratesEvenWhenTheRecordIsAbsent(): void
    {
        [$serverRequest, $lazySession] = $this->lazySessionRequest();

        (new SessionTokenStorage())->delete(new AuthToken('missing', 'session', []), $serverRequest);

        self::assertTrue($lazySession->isRegenerated());
    }

    #[Test]
    public function createKeepsTheTokenRecordOnAPlainSession(): void
    {
        $serverRequest       = $this->psr7Factory->createServerRequest('POST', '/login')->withAttribute(SessionInterface::class, new Session([]));
        $sessionTokenStorage = new SessionTokenStorage();

        $token = $sessionTokenStorage->create([
            'user_id' => 1,
        ], null, $serverRequest);

        self::assertNotNull($sessionTokenStorage->load($token->getId(), $serverRequest));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{ServerRequestInterface, LazySession}
     */
    private function lazySessionRequest(array $data = []): array
    {
        $serverRequest = $this->psr7Factory->createServerRequest('POST', '/');
        $lazySession   = new LazySession(new readonly class($data) implements SessionPersistenceInterface {
            /**
             * @param array<string, mixed> $data
             */
            public function __construct(private array $data) {}

            public function initializeSessionFromRequest(ServerRequestInterface $request): SessionInterface
            {
                return new Session($this->data);
            }

            public function persistSession(SessionInterface $session, ResponseInterface $response): ResponseInterface
            {
                return $response;
            }
        }, $serverRequest);

        return [$serverRequest->withAttribute(SessionInterface::class, $lazySession), $lazySession];
    }

    private function requestWithSession(): ServerRequestInterface
    {
        return $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withAttribute(SessionInterface::class, $this->inMemorySession)
        ;
    }
}
