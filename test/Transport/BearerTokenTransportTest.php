<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class BearerTokenTransportTest extends TestCase
{
    private BearerTokenTransport $transport;
    private Psr7Factory $factory;

    protected function setUp(): void
    {
        $this->transport = new BearerTokenTransport();
        $this->factory = new Psr7Factory();
    }

    #[Test]
    public function implementsTokenTransportInterface(): void
    {
        self::assertInstanceOf(TokenTransportInterface::class, $this->transport);
    }

    #[Test]
    public function fetchExtractsTokenFromAuthorizationHeader(): void
    {
        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Bearer my-token-value')
        ;

        self::assertSame('my-token-value', $this->transport->fetch($request));
    }

    #[Test]
    public function fetchReturnsNullWithoutHeader(): void
    {
        $request = $this->factory->createServerRequest('GET', '/');

        self::assertNull($this->transport->fetch($request));
    }

    #[Test]
    public function fetchReturnsNullForEmptyHeader(): void
    {
        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', '')
        ;

        self::assertNull($this->transport->fetch($request));
    }

    #[Test]
    public function fetchReturnsNullForInvalidScheme(): void
    {
        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Basic token')
        ;

        self::assertNull($this->transport->fetch($request));
    }

    #[Test]
    public function fetchWithCustomHeaderAndScheme(): void
    {
        $transport = new BearerTokenTransport('X-Auth-Token', 'Token');
        $request = $this->factory
            ->createServerRequest('GET', '/')
            ->withHeader('X-Auth-Token', 'Token abc123')
        ;

        self::assertSame('abc123', $transport->fetch($request));
    }

    #[Test]
    public function attachAddsAuthorizationHeader(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getId')->willReturn('attached-token');

        $response = $this->factory->createResponse();
        $result = $this->transport->attach($response, $token);

        self::assertSame('Bearer attached-token', $result->getHeaderLine('Authorization'));
    }

    #[Test]
    public function detachRemovesAuthorizationHeader(): void
    {
        $response = $this->factory
            ->createResponse()
            ->withHeader('Authorization', 'Bearer old-token')
        ;

        $result = $this->transport->detach($response);

        self::assertFalse($result->hasHeader('Authorization'));
    }
}
