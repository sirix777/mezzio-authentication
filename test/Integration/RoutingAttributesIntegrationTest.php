<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Integration;

use Mezzio\Router\Route;
use Mezzio\Router\RouteCollectorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
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
        $routeCollector = $this->createCollector();

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
    ): AttributeRouteProvider {
        return new AttributeRouteProvider(
            $attributeRouteExtractor,
            $classes,
            new DuplicateRouteResolver(),
            new MiddlewarePipelineFactory(new ArrayContainer(), new ServiceMiddlewareResolver()),
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
        $cacheFile = sys_get_temp_dir() . '/mezzio-authentication-routing-attributes-' . uniqid('', true) . '.php';
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
                $route = new Route($path, $middleware, $methods, $name);
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
