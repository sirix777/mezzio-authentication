<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class BearerTokenTransportTest extends TestCase
{
    private BearerTokenTransport $bearerTokenTransport;
    private Psr7Factory $psr7Factory;

    protected function setUp(): void
    {
        $this->bearerTokenTransport = new BearerTokenTransport();
        $this->psr7Factory          = new Psr7Factory();
    }

    #[Test]
    public function implementsTokenTransportInterface(): void
    {
        self::assertInstanceOf(TokenTransportInterface::class, $this->bearerTokenTransport);
    }

    #[Test]
    public function fetchExtractsTokenFromAuthorizationHeader(): void
    {
        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Bearer my-token-value')
        ;

        self::assertSame('my-token-value', $this->bearerTokenTransport->fetch($serverRequest));
    }

    #[Test]
    public function fetchReturnsNullWithoutHeader(): void
    {
        $serverRequest = $this->psr7Factory->createServerRequest('GET', '/');

        self::assertNull($this->bearerTokenTransport->fetch($serverRequest));
    }

    #[Test]
    public function fetchReturnsNullForEmptyHeader(): void
    {
        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', '')
        ;

        self::assertNull($this->bearerTokenTransport->fetch($serverRequest));
    }

    #[Test]
    public function fetchReturnsNullForInvalidScheme(): void
    {
        $serverRequest = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Basic token')
        ;

        self::assertNull($this->bearerTokenTransport->fetch($serverRequest));
    }

    #[Test]
    public function fetchWithCustomHeaderAndScheme(): void
    {
        $bearerTokenTransport = new BearerTokenTransport('X-Auth-Token', 'Token');
        $serverRequest        = $this->psr7Factory
            ->createServerRequest('GET', '/')
            ->withHeader('X-Auth-Token', 'Token abc123')
        ;

        self::assertSame('abc123', $bearerTokenTransport->fetch($serverRequest));
    }

    #[Test]
    public function attachAddsAuthorizationHeader(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getId')->willReturn('attached-token');

        $response = $this->psr7Factory->createResponse();
        $result   = $this->bearerTokenTransport->attach($response, $token);

        self::assertSame('Bearer attached-token', $result->getHeaderLine('Authorization'));
    }

    #[Test]
    public function detachRemovesAuthorizationHeader(): void
    {
        $response = $this->psr7Factory
            ->createResponse()
            ->withHeader('Authorization', 'Bearer old-token')
        ;

        $result = $this->bearerTokenTransport->detach($response);

        self::assertFalse($result->hasHeader('Authorization'));
    }

    /**
     * @param non-empty-string $header
     */
    #[Test]
    #[DataProvider('authorizationHeaders')]
    public function fetchParsesTheSchemeStrictly(string $header, ?string $expected): void
    {
        $serverRequest = $this->psr7Factory->createServerRequest('GET', '/')->withHeader('Authorization', $header);

        self::assertSame($expected, $this->bearerTokenTransport->fetch($serverRequest));
    }

    /**
     * @return iterable<string, array{non-empty-string, ?string}>
     */
    public static function authorizationHeaders(): iterable
    {
        yield 'scheme is case-insensitive' => ['bearer token-1', 'token-1'];

        yield 'surrounding whitespace is trimmed' => ["  Bearer \t token-1  ", 'token-1'];

        yield 'scheme glued to the token' => ['Bearertoken-1', null];

        yield 'scheme only' => ['Bearer    ', null];

        yield 'scheme as a prefix of another scheme' => ['BearerX token-1', null];
    }
}
