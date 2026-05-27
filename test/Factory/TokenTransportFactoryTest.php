<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Factory\TokenTransportFactory;
use Sirix\Mezzio\Authentication\Token\AuthToken;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

final class TokenTransportFactoryTest extends TestCase
{
    #[Test]
    public function createsBearerTransportByDefault(): void
    {
        $transport = (new TokenTransportFactory())(new ArrayContainer([
            'config' => [],
        ]));

        $request = (new Psr7Factory())
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Bearer bearer-token')
        ;

        self::assertSame('bearer-token', $transport->fetch($request));
    }

    #[Test]
    public function readsCookieTransportConfiguration(): void
    {
        $transport = (new TokenTransportFactory())(new ArrayContainer([
            'config' => [
                'authentication' => [
                    'transport' => [
                        'driver' => 'cookie',
                    ],
                    'cookie' => [
                        'name' => 'app_auth',
                        'path' => '/admin',
                        'domain' => 'example.com',
                        'secure' => true,
                        'http_only' => true,
                        'same_site' => 'Strict',
                    ],
                ],
            ],
        ]));

        $factory = new Psr7Factory();
        $request = $factory
            ->createServerRequest('GET', '/')
            ->withCookieParams(['app_auth' => 'cookie-token'])
        ;

        self::assertSame('cookie-token', $transport->fetch($request));

        $response = $transport->attach(
            $factory->createResponse(),
            new AuthToken('attached-token', 'session', [], 1_800_000_000),
        );

        self::assertStringContainsString('app_auth=attached-token', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('Path=/admin', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('Domain=example.com', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('Secure', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('HttpOnly', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('SameSite=Strict', $response->getHeaderLine('Set-Cookie'));
    }
}
