<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Attribute;

use Attribute;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
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
    public function returnsEmptyDefaults(): void
    {
        $guestOnly = new GuestOnly();
        self::assertSame([], $guestOnly->getDefaults());
    }

    #[Test]
    public function isAttribute(): void
    {
        $reflectionClass = new ReflectionClass(GuestOnly::class);
        $attributes = $reflectionClass->getAttributes();

        self::assertCount(1, $attributes);
        self::assertSame(Attribute::class, $attributes[0]->getName());
    }
}
