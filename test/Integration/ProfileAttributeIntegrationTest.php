<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Integration;

use LogicException;
use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Exception\AlreadyAuthenticatedException;
use Sirix\Mezzio\Authentication\Exception\UnknownAuthenticationProfileException;
use Sirix\Mezzio\Authentication\Factory\AuthenticationProfileProviderFactory;
use Sirix\Mezzio\Authentication\Factory\ProfileMiddlewareFactory;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\TokenStorageProvider;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use Sirix\Mezzio\Routing\Attributes\AttributeRouteProvider;
use Sirix\Mezzio\Routing\Attributes\Cache\NullRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheGenerator;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheLoader;
use Sirix\Mezzio\Routing\Attributes\Cache\RouteCacheStorage;
use Sirix\Mezzio\Routing\Attributes\CompiledRouteRegistrarCache;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\Extractor\ClassEligibilityValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\MethodSignatureValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteAttributeReader;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDefinitionBuilder;
use Sirix\Mezzio\Routing\Attributes\MiddlewarePipelineFactory;
use Sirix\Mezzio\Routing\Attributes\ServiceMiddlewareResolver;
use SirixTest\Mezzio\Authentication\Integration\Fixture\AuthenticatedProfileRouteHandler;
use SirixTest\Mezzio\Authentication\Integration\Fixture\AuthenticatedUnknownProfileRouteHandler;
use SirixTest\Mezzio\Authentication\Integration\Fixture\GuestOnlyProfileRouteHandler;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;
use SirixTest\Mezzio\Authentication\Support\InMemoryTokenStorage;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

use function file_exists;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class ProfileAttributeIntegrationTest extends TestCase
{
    /** @var list<string> */
    private array $cacheFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cacheFiles as $cacheFile) {
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        }
    }

    #[Test]
    public function authenticatedProfileAttributeUsesNamedProfileInNonCachedMode(): void
    {
        $container      = $this->profileRouteContainer();
        $routeCollector = $this->createCollector();

        $this->createProvider(
            [
                AuthenticatedProfileRouteHandler::class,
                GuestOnlyProfileRouteHandler::class,
                AuthenticatedUnknownProfileRouteHandler::class,
            ],
            new NullRouteRegistrarCache(),
            $this->createExtractor(),
            $container,
        )->registerRoutes($routeCollector);

        $this->assertProfileRoutesExecute($routeCollector, $container);
    }

    #[Test]
    public function authenticatedProfileAttributeUsesNamedProfileAfterCacheWarmAndReload(): void
    {
        $compiledRouteRegistrarCache  = $this->createCompiledCache();
        $container                    = $this->profileRouteContainer();
        $routeCollector               = $this->createCollector();

        $this->createProvider(
            [
                AuthenticatedProfileRouteHandler::class,
                GuestOnlyProfileRouteHandler::class,
                AuthenticatedUnknownProfileRouteHandler::class,
            ],
            $compiledRouteRegistrarCache,
            $this->createExtractor(),
            $container,
        )->registerRoutes($routeCollector);

        self::assertNotSame([], $routeCollector->getRoutes());

        $cachedCollector = $this->createCollector();
        $unusedExtractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $unusedExtractor->expects($this->never())->method('extract');

        $this->createProvider([], $compiledRouteRegistrarCache, $unusedExtractor, $container)
            ->registerRoutes($cachedCollector)
        ;

        $this->assertProfileRoutesExecute($cachedCollector, $container);
    }

    #[Test]
    public function unknownProfileAttributeThrowsAtRequestTime(): void
    {
        $container      = $this->profileRouteContainer();
        $routeCollector = $this->createCollector();

        $this->createProvider(
            [AuthenticatedUnknownProfileRouteHandler::class],
            new NullRouteRegistrarCache(),
            $this->createExtractor(),
            $container,
        )->registerRoutes($routeCollector);

        $psr7Factory = new Psr7Factory();
        $routes      = $routeCollector->getRoutes();
        $route       = $this->routeByName($routes, 'integration.authenticated_unknown');

        $this->expectException(UnknownAuthenticationProfileException::class);

        $route->process(
            $psr7Factory->createServerRequest('GET', '/integration/authenticated-unknown')
                ->withHeader('Authorization', 'Bearer anything'),
            new ProfileUnreachableRequestHandler(),
        );
    }

    private function assertProfileRoutesExecute(RouteCollectorInterface $routeCollector, ContainerInterface $container): void
    {
        $psr7Factory = new Psr7Factory();
        $routes      = $routeCollector->getRoutes();

        $apiRoute = $this->routeByName($routes, 'integration.authenticated_api');
        $apiRoute->process(
            $psr7Factory->createServerRequest('GET', '/integration/authenticated-api')
                ->withHeader('Authorization', 'Bearer redis-1'),
            new ProfileUnreachableRequestHandler(),
        );

        /** @var ProfileAttributeRouteRequestHandler $apiHandler */
        $apiHandler = $container->get(AuthenticatedProfileRouteHandler::class);
        self::assertSame('redis', $apiHandler->token()?->getStorage());

        $webGuestRoute = $this->routeByName($routes, 'integration.guest_web');
        $webGuestRoute->process(
            $psr7Factory->createServerRequest('GET', '/integration/guest-web')
                ->withCookieParams([
                    'web_auth' => 'invalid',
                ]),
            new ProfileUnreachableRequestHandler(),
        );

        /** @var ProfileAttributeRouteRequestHandler $guestHandler */
        $guestHandler = $container->get(GuestOnlyProfileRouteHandler::class);
        self::assertNull($guestHandler->token());

        $this->expectException(AlreadyAuthenticatedException::class);

        $webGuestRoute->process(
            $psr7Factory->createServerRequest('GET', '/integration/guest-web')
                ->withCookieParams([
                    'web_auth' => 'web-1',
                ]),
            new ProfileUnreachableRequestHandler(),
        );
    }

    private function profileRouteContainer(): ContainerInterface
    {
        $apiStorage = new InMemoryTokenStorage('redis');
        $apiStorage->create([]);
        $webStorage = new InMemoryTokenStorage('web');
        $webStorage->create([]);
        $actor         = $this->createStub(ActorInterface::class);
        $authenticator = new class($actor) implements AuthenticatorInterface {
            public function __construct(private readonly ActorInterface $actor) {}

            public function authenticate(?TokenInterface $token): AuthenticationContext
            {
                return new AuthenticationContext($token, $token instanceof TokenInterface ? $this->actor : null);
            }
        };
        $profiles = (new AuthenticationProfileProviderFactory())(new ArrayContainer([
            'config'                             => [
                'authentication' => [
                    'default_profile' => 'api',
                    'profiles'        => [
                        'web' => [
                            'transport'         => 'cookie',
                            'storage'           => 'web',
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
                'null'  => new NullTokenStorage(),
                'web'   => $webStorage,
                'redis' => $apiStorage,
            ]),
            TokenTransportInterface::class       => new BearerTokenTransport(),
        ]));

        return new ArrayContainer([
            AuthenticationProfileProviderInterface::class  => $profiles,
            ProfileMiddlewareFactory::class                => new ProfileMiddlewareFactory(),
            GuestOnlyMiddleware::class                     => new GuestOnlyMiddleware(),
            AuthenticatedProfileRouteHandler::class        => new ProfileAttributeRouteRequestHandler(),
            GuestOnlyProfileRouteHandler::class            => new ProfileAttributeRouteRequestHandler(),
            AuthenticatedUnknownProfileRouteHandler::class => new ProfileAttributeRouteRequestHandler(),
        ]);
    }

    /**
     * @param list<Route> $routes
     */
    private function routeByName(array $routes, string $name): Route
    {
        foreach ($routes as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }

        self::fail("Route '{$name}' was not registered.");
    }

    /**
     * @param list<class-string> $classes
     */
    private function createProvider(
        array $classes,
        CompiledRouteRegistrarCache|NullRouteRegistrarCache $cache,
        AttributeRouteExtractorInterface $attributeRouteExtractor,
        ContainerInterface $container,
    ): AttributeRouteProvider {
        return new AttributeRouteProvider(
            $attributeRouteExtractor,
            $classes,
            new DuplicateRouteResolver(),
            new MiddlewarePipelineFactory($container, new ServiceMiddlewareResolver()),
            $cache,
        );
    }

    private function createExtractor(): AttributeRouteExtractor
    {
        $routeAttributeReader = new RouteAttributeReader();

        return new AttributeRouteExtractor(
            new ClassEligibilityValidator(),
            $routeAttributeReader,
            new RouteDefinitionBuilder(
                $routeAttributeReader,
                new MethodSignatureValidator(),
                new RouteDataNormalizer(),
            ),
        );
    }

    private function createCompiledCache(): CompiledRouteRegistrarCache
    {
        $cacheFile          = sys_get_temp_dir() . '/mezzio-authentication-profile-attributes-' . uniqid('', true) . '.php';
        $this->cacheFiles[] = $cacheFile;

        return new CompiledRouteRegistrarCache(
            $cacheFile,
            new RouteCacheGenerator(),
            new RouteCacheStorage(),
            new RouteCacheLoader(),
        );
    }

    private function createCollector(): RouteCollectorInterface
    {
        return new class implements RouteCollectorInterface {
            /** @var list<Route> */
            private array $routes = [];

            public function route(string $path, MiddlewareInterface $middleware, ?array $methods = null, ?string $name = null): Route
            {
                $route          = new Route($path, $middleware, $methods, $name);
                $this->routes[] = $route;

                return $route;
            }

            public function get(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['GET'], $name);
            }

            public function post(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['POST'], $name);
            }

            public function put(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PUT'], $name);
            }

            public function patch(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['PATCH'], $name);
            }

            public function delete(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, ['DELETE'], $name);
            }

            public function any(string $path, MiddlewareInterface $middleware, ?string $name = null): Route
            {
                return $this->route($path, $middleware, null, $name);
            }

            public function getRoutes(): array
            {
                return $this->routes;
            }
        };
    }
}

/** @internal */
final class ProfileAttributeRouteRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return (new Psr7Factory())->createResponse(200);
    }

    public function token(): ?TokenInterface
    {
        $token = $this->request?->getAttribute(AuthenticationAttributes::Token->value);

        return $token instanceof TokenInterface ? $token : null;
    }
}

/** @internal */
final class ProfileUnreachableRequestHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new LogicException('Route handler should resolve from the route pipeline.');
    }
}
