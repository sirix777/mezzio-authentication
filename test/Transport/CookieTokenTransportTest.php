<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Transport\CookieTokenTransport;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class CookieTokenTransportTest extends TestCase
{
    private CookieTokenTransport $cookieTokenTransport;
    private Psr7Factory $psr7Factory;

    protected function setUp(): void
    {
        $this->cookieTokenTransport = new CookieTokenTransport();
        $this->psr7Factory = new Psr7Factory();
    }

    #[Test]
    public function implementsTokenTransportInterface(): void
    {
        self::assertInstanceOf(TokenTransportInterface::class, $this->cookieTokenTransport);
    }

    #[Test]
    public function fetchExtractsTokenFromCookie(): void
    {
        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withCookieParams(['mezzio_authentication' => 'cookie-token'])
        ;

        self::assertSame('cookie-token', $this->cookieTokenTransport->fetch($serverRequest));
    }

    #[Test]
    public function fetchReturnsNullWithoutCookie(): void
    {
        $serverRequest = $this->psr7Factory->createServerRequest('GET', '/');

        self::assertNull($this->cookieTokenTransport->fetch($serverRequest));
    }

    #[Test]
    public function fetchReturnsNullForEmptyCookie(): void
    {
        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withCookieParams(['mezzio_authentication' => ''])
        ;

        self::assertNull($this->cookieTokenTransport->fetch($serverRequest));
    }

    #[Test]
    public function fetchWithCustomCookieName(): void
    {
        $cookieTokenTransport = new CookieTokenTransport('auth_token');
        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withCookieParams(['auth_token' => 'custom'])
        ;

        self::assertSame('custom', $cookieTokenTransport->fetch($serverRequest));
    }

    #[Test]
    public function attachSetsSetCookieHeader(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getId')->willReturn('attached-token');
        $token->method('getExpiresAt')->willReturn(null);

        $response = $this->psr7Factory->createResponse();
        $result = $this->cookieTokenTransport->attach($response, $token);

        $cookieHeader = $result->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('mezzio_authentication=', $cookieHeader);
        self::assertStringContainsString('Path=/', $cookieHeader);
        self::assertStringContainsString('HttpOnly', $cookieHeader);
        self::assertStringContainsString('SameSite=Lax', $cookieHeader);
    }

    #[Test]
    public function detachSetsExpiredCookie(): void
    {
        $response = $this->psr7Factory->createResponse();
        $result = $this->cookieTokenTransport->detach($response);

        $cookieHeader = $result->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('mezzio_authentication=deleted', $cookieHeader);
        self::assertStringContainsString('Expires=', $cookieHeader);
    }
}
