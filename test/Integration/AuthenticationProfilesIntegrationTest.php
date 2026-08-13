<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Integration;

use Mezzio\Session\SessionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Factory\AuthenticationProfileProviderFactory;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\TokenStorageProvider;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;
use SirixTest\Mezzio\Authentication\Support\InMemorySession;
use SirixTest\Mezzio\Authentication\Support\InMemoryTokenStorage;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

use function preg_match;

final class AuthenticationProfilesIntegrationTest extends TestCase
{
    private Psr7Factory $psr7Factory;

    protected function setUp(): void
    {
        $this->psr7Factory = new Psr7Factory();
    }

    #[Test]
    public function webProfileCompletesCookieSessionLifecycleWithoutUsingTheApiStorage(): void
    {
        $inMemorySession              = new InMemorySession();
        $inMemoryTokenStorage         = new InMemoryTokenStorage('redis');
        $sessionTokenStorage          = new SessionTokenStorage();
        $profiles                     = $this->profiles($sessionTokenStorage, $inMemoryTokenStorage);
        $authenticationProfile        = $profiles->get('web');
        $serverRequest                = $this->psr7Factory
            ->createServerRequest('POST', '/login')
            ->withAttribute(SessionInterface::class, $inMemorySession)
        ;

        $loginResponse = $authenticationProfile->manager()->login($serverRequest, $this->psr7Factory->createResponse(204), []);
        preg_match('/web_auth=([^;]+)/', $loginResponse->getHeaderLine('Set-Cookie'), $matches);
        $tokenId = $matches[1] ?? null;

        self::assertIsString($tokenId);
        self::assertStringContainsString('web_auth=', $loginResponse->getHeaderLine('Set-Cookie'));

        $profileRequestHandler = new ProfileRequestHandler($this->psr7Factory);
        $authenticationProfile->authenticateMiddleware()->process(
            $serverRequest->withCookieParams([
                'web_auth' => $tokenId,
            ]),
            $profileRequestHandler,
        );

        self::assertInstanceOf(ServerRequestInterface::class, $profileRequestHandler->request);
        self::assertTrue($profileRequestHandler->request->getAttribute(AuthenticationAttributes::Context->value)->check());
        self::assertInstanceOf(ActorInterface::class, $profileRequestHandler->request->getAttribute(AuthenticationAttributes::Actor->value));
        self::assertSame([], $inMemoryTokenStorage->operations);

        $logoutResponse = $authenticationProfile->manager()->logout($profileRequestHandler->request, $loginResponse);

        self::assertStringContainsString('web_auth=deleted', $logoutResponse->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('Expires=', $logoutResponse->getHeaderLine('Set-Cookie'));
        self::assertNull($sessionTokenStorage->load($tokenId, $serverRequest));
        self::assertSame([], $inMemoryTokenStorage->operations);
    }

    #[Test]
    public function apiProfileCompletesBearerRedisLikeLifecycleAndCanBeUsedForAManualRoute(): void
    {
        $inMemoryTokenStorage         = new InMemoryTokenStorage('redis');
        $profiles                     = $this->profiles(new SessionTokenStorage(), $inMemoryTokenStorage);
        $authenticationProfile        = $profiles->get('api');
        $serverRequest                = $this->psr7Factory->createServerRequest('POST', '/api/login');

        $loginResponse               = $authenticationProfile->manager()->login($serverRequest, $this->psr7Factory->createResponse(204), []);
        $authorization               = $loginResponse->getHeaderLine('Authorization');
        $profileRequestHandler       = new ProfileRequestHandler($this->psr7Factory);

        $authenticationProfile->authenticateMiddleware()->process(
            $serverRequest->withHeader('Authorization', $authorization),
            $profileRequestHandler,
        );

        self::assertInstanceOf(ServerRequestInterface::class, $profileRequestHandler->request);
        self::assertTrue($profileRequestHandler->request->getAttribute(AuthenticationAttributes::Context->value)->check());
        self::assertInstanceOf(ActorInterface::class, $profileRequestHandler->request->getAttribute(AuthenticationAttributes::Actor->value));
        self::assertSame(['create', 'load'], $inMemoryTokenStorage->operations);

        $logoutResponse = $authenticationProfile->manager()->logout($profileRequestHandler->request, $loginResponse);

        self::assertSame('', $logoutResponse->getHeaderLine('Authorization'));
        self::assertSame(['create', 'load', 'delete'], $inMemoryTokenStorage->operations);
    }

    #[Test]
    public function profilesInOneContainerKeepCookieAndBearerCredentialsIsolated(): void
    {
        $inMemorySession              = new InMemorySession();
        $inMemoryTokenStorage         = new InMemoryTokenStorage('redis');
        $profiles                     = $this->profiles(new SessionTokenStorage(), $inMemoryTokenStorage);
        $authenticationProfile        = $profiles->get('web');
        $api                          = $profiles->get('api');
        $serverRequest                = $this->psr7Factory->createServerRequest('POST', '/login')->withAttribute(SessionInterface::class, $inMemorySession);
        $apiRequest                   = $this->psr7Factory->createServerRequest('POST', '/api/login');

        $webResponse = $authenticationProfile->manager()->login($serverRequest, $this->psr7Factory->createResponse(), []);
        $apiResponse = $api->manager()->login($apiRequest, $this->psr7Factory->createResponse(), []);
        preg_match('/web_auth=([^;]+)/', $webResponse->getHeaderLine('Set-Cookie'), $matches);
        $cookieToken = $matches[1] ?? '';

        $webHandler      = new ProfileRequestHandler($this->psr7Factory);
        $apiHandler      = new ProfileRequestHandler($this->psr7Factory);
        $combinedRequest = $serverRequest
            ->withCookieParams([
                'web_auth' => $cookieToken,
            ])
            ->withHeader('Authorization', $apiResponse->getHeaderLine('Authorization'))
        ;

        $authenticationProfile->authenticateMiddleware()->process($combinedRequest, $webHandler);
        $api->authenticateMiddleware()->process($combinedRequest, $apiHandler);

        self::assertSame('session', $webHandler->token()?->getStorage());
        self::assertSame('redis', $apiHandler->token()?->getStorage());
        self::assertSame(['create', 'load'], $inMemoryTokenStorage->operations);
    }

    private function profiles(
        SessionTokenStorage $sessionTokenStorage,
        InMemoryTokenStorage $inMemoryTokenStorage
    ): AuthenticationProfileProviderInterface {
        $actor         = $this->createStub(ActorInterface::class);
        $authenticator = new class($actor) implements AuthenticatorInterface {
            public function __construct(private readonly ActorInterface $actor) {}

            public function authenticate(?TokenInterface $token): AuthenticationContext
            {
                return new AuthenticationContext($token, $token instanceof TokenInterface ? $this->actor : null);
            }
        };

        return (new AuthenticationProfileProviderFactory())(new ArrayContainer([
            'config'                             => [
                'authentication' => [
                    'default_profile' => 'web',
                    'profiles'        => [
                        'web' => [
                            'transport'         => 'cookie',
                            'storage'           => 'session',
                            'transport_options' => [
                                'name' => 'web_auth',
                            ],
                        ],
                        'api' => [
                            'transport' => 'bearer',
                            'storage'   => 'redis',
                        ],
                    ],
                ],
            ],
            AuthenticatorInterface::class        => $authenticator,
            TokenStorageProviderInterface::class => new TokenStorageProvider('null', [
                'null'    => new NullTokenStorage(),
                'session' => $sessionTokenStorage,
                'redis'   => $inMemoryTokenStorage,
            ]),
            TokenTransportInterface::class       => new BearerTokenTransport(),
        ]));
    }
}

/** @internal */
final class ProfileRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    public function __construct(private readonly Psr7Factory $psr7Factory) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return $this->psr7Factory->createResponse(200);
    }

    public function token(): ?TokenInterface
    {
        $token = $this->request?->getAttribute(AuthenticationAttributes::Token->value);

        return $token instanceof TokenInterface ? $token : null;
    }
}
