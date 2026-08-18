<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Attribute;

use Attribute;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Authentication\Factory\ProfileMiddlewareFactory;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

final class GuestOnlyTest extends TestCase
{
    #[Test]
    public function implementsRouteAttributeModifierInterface(): void
    {
        $guestOnly = new GuestOnly();
        self::assertInstanceOf(RouteAttributeModifierInterface::class, $guestOnly);
    }

    #[Test]
    public function returnsBothMiddlewares(): void
    {
        $guestOnly = new GuestOnly();
        self::assertSame([
            OptionalAuthenticateMiddleware::class,
            GuestOnlyMiddleware::class,
        ], $guestOnly->getMiddleware());
    }

    #[Test]
    public function returnsMixedListForNamedProfile(): void
    {
        $guestOnly  = new GuestOnly(profile: 'web');
        $middleware = $guestOnly->getMiddleware();

        self::assertCount(2, $middleware);
        self::assertInstanceOf(MiddlewareSpecification::class, $middleware[0]);
        self::assertSame(OptionalAuthenticateMiddleware::class, $middleware[0]->service);
        self::assertSame(ProfileMiddlewareFactory::class, $middleware[0]->factory);
        self::assertSame([
            'profile' => 'web',
        ], $middleware[0]->arguments);
        self::assertSame(GuestOnlyMiddleware::class, $middleware[1]);
    }

    #[Test]
    public function returnsEmptyDefaults(): void
    {
        $guestOnly = new GuestOnly();
        self::assertSame([], $guestOnly->getDefaults());
    }

    #[Test]
    public function returnsEmptyDefaultsForNamedProfile(): void
    {
        $guestOnly = new GuestOnly(profile: 'web');
        self::assertSame([], $guestOnly->getDefaults());
    }

    #[Test]
    public function isAttribute(): void
    {
        $reflectionClass = new ReflectionClass(GuestOnly::class);
        $attributes      = $reflectionClass->getAttributes();

        self::assertCount(1, $attributes);
        self::assertSame(Attribute::class, $attributes[0]->getName());
    }
}
