<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Transport;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Transport\CookieTokenTransport;

final class CookieTokenTransportTest extends TestCase
{
    private CookieTokenTransport $transport;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->transport = new CookieTokenTransport();
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function implementsTokenTransportInterface(): void
    {
        self::assertInstanceOf(TokenTransportInterface::class, $this->transport);
    }

    #[Test]
    public function fetchExtractsTokenFromCookie(): void
    {
        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withCookieParams(['mezzio_authentication' => 'cookie-token'])
        ;

        self::assertSame('cookie-token', $this->transport->fetch($request));
    }

    #[Test]
    public function fetchReturnsNullWithoutCookie(): void
    {
        $request = $this->factory->createServerRequest('GET', '/');

        self::assertNull($this->transport->fetch($request));
    }

    #[Test]
    public function fetchReturnsNullForEmptyCookie(): void
    {
        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withCookieParams(['mezzio_authentication' => ''])
        ;

        self::assertNull($this->transport->fetch($request));
    }

    #[Test]
    public function fetchWithCustomCookieName(): void
    {
        $transport = new CookieTokenTransport('auth_token');
        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withCookieParams(['auth_token' => 'custom'])
        ;

        self::assertSame('custom', $transport->fetch($request));
    }

    #[Test]
    public function attachSetsSetCookieHeader(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getId')->willReturn('attached-token');
        $token->method('getExpiresAt')->willReturn(null);

        $response = $this->factory->createResponse();
        $result = $this->transport->attach($response, $token);

        $cookieHeader = $result->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('mezzio_authentication=', $cookieHeader);
        self::assertStringContainsString('Path=/', $cookieHeader);
        self::assertStringContainsString('HttpOnly', $cookieHeader);
        self::assertStringContainsString('SameSite=Lax', $cookieHeader);
    }

    #[Test]
    public function detachSetsExpiredCookie(): void
    {
        $response = $this->factory->createResponse();
        $result = $this->transport->detach($response);

        $cookieHeader = $result->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('mezzio_authentication=deleted', $cookieHeader);
        self::assertStringContainsString('Expires=', $cookieHeader);
    }
}
