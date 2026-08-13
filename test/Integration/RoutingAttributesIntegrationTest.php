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
use Sirix\Mezzio\Authentication\Factory\AuthenticationProfileProviderFactory;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
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
use SirixTest\Mezzio\Authentication\Integration\Fixture\AuthenticatedRouteHandler;
use SirixTest\Mezzio\Authentication\Integration\Fixture\GuestOnlyRouteHandler;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;
use SirixTest\Mezzio\Authentication\Support\InMemoryTokenStorage;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;

use function file_exists;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class RoutingAttributesIntegrationTest extends TestCase
{
    private const MIDDLEWARE_DISPLAY = 'sirix_routing_attributes.middleware_display';

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
    public function routingAttributesRegistersAuthenticationMiddlewareInNonCachedMode(): void
    {
        $routeCollector = $this->createCollector();

        $this->createProvider(
            [
                AuthenticatedRouteHandler::class,
                GuestOnlyRouteHandler::class,
            ],
            new NullRouteRegistrarCache(),
            $this->createExtractor(),
        )->registerRoutes($routeCollector);

        $this->assertRegisteredRoutes($routeCollector->getRoutes());
    }

    #[Test]
    public function routingAttributesRegistersAuthenticationMiddlewareInCachedMode(): void
    {
        $compiledRouteRegistrarCache = $this->createCompiledCache();
        $routeCollector              = $this->createCollector();

        $this->createProvider(
            [
                AuthenticatedRouteHandler::class,
                GuestOnlyRouteHandler::class,
            ],
            $compiledRouteRegistrarCache,
            $this->createExtractor(),
        )->registerRoutes($routeCollector);

        self::assertNotSame([], $routeCollector->getRoutes());

        $cachedCollector = $this->createCollector();
        $unusedExtractor = $this->createMock(AttributeRouteExtractorInterface::class);
        $unusedExtractor
            ->expects($this->never())
            ->method('extract')
        ;

        $this->createProvider([], $compiledRouteRegistrarCache, $unusedExtractor)->registerRoutes($cachedCollector);

        $this->assertRegisteredRoutes($cachedCollector->getRoutes());
    }

    #[Test]
    public function nonCachedAttributeRoutesUseTheApiDefaultProfileAndManualNamedMiddleware(): void
    {
        $this->assertExecutableProfileRoutes(new NullRouteRegistrarCache());
    }

    #[Test]
    public function cachedAttributeRoutesUseTheApiDefaultProfileAndManualNamedMiddleware(): void
    {
        $compiledRouteRegistrarCache     = $this->createCompiledCache();
        $container                       = $this->profileRouteContainer();
        $routeCollector                  = $this->createCollector();

        $this->createProvider(
            [AuthenticatedRouteHandler::class, GuestOnlyRouteHandler::class],
            $compiledRouteRegistrarCache,
            $this->createExtractor(),
            $container,
        )->registerRoutes($routeCollector);

        $cachedCollector = $this->createCollector();
        $this->createProvider([], $compiledRouteRegistrarCache, $this->createMock(AttributeRouteExtractorInterface::class), $container)
            ->registerRoutes($cachedCollector)
        ;

        $this->assertExecutableProfileRoutes($compiledRouteRegistrarCache, $container, $cachedCollector);
    }

    private function assertExecutableProfileRoutes(
        CompiledRouteRegistrarCache|NullRouteRegistrarCache $cache,
        ?ContainerInterface $container = null,
        ?RouteCollectorInterface $routeCollector = null,
    ): void {
        $container ??= $this->profileRouteContainer();
        $routeCollector ??= $this->createCollector();

        if ($cache instanceof NullRouteRegistrarCache) {
            $this->createProvider(
                [AuthenticatedRouteHandler::class, GuestOnlyRouteHandler::class],
                $cache,
                $this->createExtractor(),
                $container,
            )->registerRoutes($routeCollector);
        }

        $psr7Factory   = new Psr7Factory();
        $routes        = $routeCollector->getRoutes();
        $route         = $this->routeByName($routes, 'integration.authenticated');
        $guestOnly     = $this->routeByName($routes, 'integration.guest');

        $route->process(
            $psr7Factory->createServerRequest('GET', '/integration/authenticated')->withHeader('Authorization', 'Bearer redis-1'),
            new UnreachableRequestHandler(),
        );
        $guestOnly->process(
            $psr7Factory->createServerRequest('GET', '/integration/guest')->withCookieParams([
                'web_auth' => 'web-credential',
            ]),
            new UnreachableRequestHandler(),
        );

        /** @var AttributeRouteRequestHandler $authenticatedHandler */
        $authenticatedHandler = $container->get(AuthenticatedRouteHandler::class);

        /** @var AttributeRouteRequestHandler $guestOnlyHandler */
        $guestOnlyHandler = $container->get(GuestOnlyRouteHandler::class);

        self::assertSame('redis', $authenticatedHandler->token()?->getStorage());
        self::assertNull($guestOnlyHandler->token());

        /** @var AuthenticationProfileProviderInterface $profiles */
        $profiles                      = $container->get(AuthenticationProfileProviderInterface::class);
        $attributeRouteRequestHandler  = new AttributeRouteRequestHandler();
        $manualRouteCollector          = $this->createCollector();
        $middlewarePipelineFactory     = new MiddlewarePipelineFactory(
            new ArrayContainer([
                'manual.web.authentication'    => $profiles->get('web')->authenticateMiddleware(),
                'manual.web.handler'           => $attributeRouteRequestHandler,
            ]),
            new ServiceMiddlewareResolver(),
        );
        $manualRouteCollector->get(
            '/manual/web',
            $middlewarePipelineFactory->createFromSignature(
                'manual.web.handler',
                'handle',
                ['manual.web.authentication'],
            ),
            'integration.manual.web',
        );

        $this->routeByName($manualRouteCollector->getRoutes(), 'integration.manual.web')->process(
            $psr7Factory->createServerRequest('GET', '/manual/web')->withCookieParams([
                'web_auth' => 'web-1',
            ]),
            new UnreachableRequestHandler(),
        );

        self::assertSame('web', $attributeRouteRequestHandler->token()?->getStorage());
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
            AuthenticateMiddleware::class                  => $profiles->getDefaultProfile()->authenticateMiddleware(),
            OptionalAuthenticateMiddleware::class          => $profiles->getDefaultProfile()->optionalAuthenticateMiddleware(),
            GuestOnlyMiddleware::class                     => new GuestOnlyMiddleware(),
            AuthenticatedRouteHandler::class               => new AttributeRouteRequestHandler(),
            GuestOnlyRouteHandler::class                   => new AttributeRouteRequestHandler(),
        ]);
    }

    /**
     * @param list<Route> $routes
     */
    private function assertRegisteredRoutes(array $routes): void
    {
        self::assertCount(2, $routes);

        $route = $this->routeByName($routes, 'integration.authenticated');
        self::assertSame('/integration/authenticated', $route->getPath());
        self::assertSame(['GET'], $route->getAllowedMethods());
        self::assertSame(
            AuthenticateMiddleware::class . ' -> ' . AuthenticatedRouteHandler::class . '::handle',
            $route->getOptions()[self::MIDDLEWARE_DISPLAY] ?? null,
        );

        $guest = $this->routeByName($routes, 'integration.guest');
        self::assertSame('/integration/guest', $guest->getPath());
        self::assertSame(['GET'], $guest->getAllowedMethods());
        self::assertSame(
            OptionalAuthenticateMiddleware::class . ' -> ' . GuestOnlyMiddleware::class . ' -> ' . GuestOnlyRouteHandler::class . '::handle',
            $guest->getOptions()[self::MIDDLEWARE_DISPLAY] ?? null,
        );
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
        ?ContainerInterface $container = null,
    ): AttributeRouteProvider {
        return new AttributeRouteProvider(
            $attributeRouteExtractor,
            $classes,
            new DuplicateRouteResolver(),
            new MiddlewarePipelineFactory($container ?? new ArrayContainer(), new ServiceMiddlewareResolver()),
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
        $cacheFile          = sys_get_temp_dir() . '/mezzio-authentication-routing-attributes-' . uniqid('', true) . '.php';
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
final class AttributeRouteRequestHandler implements RequestHandlerInterface
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
final class UnreachableRequestHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new LogicException('Route handler should resolve from the route pipeline.');
    }
}
