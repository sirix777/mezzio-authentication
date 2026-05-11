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
        $attribute = new GuestOnly();
        self::assertInstanceOf(RouteAttributeModifierInterface::class, $attribute);
    }

    #[Test]
    public function returnsBothMiddlewares(): void
    {
        $attribute = new GuestOnly();
        self::assertSame([
            OptionalAuthenticateMiddleware::class,
            GuestOnlyMiddleware::class,
        ], $attribute->getMiddleware());
    }

    #[Test]
    public function returnsEmptyDefaults(): void
    {
        $attribute = new GuestOnly();
        self::assertSame([], $attribute->getDefaults());
    }

    #[Test]
    public function isAttribute(): void
    {
        $reflection = new ReflectionClass(GuestOnly::class);
        $attributes = $reflection->getAttributes();

        self::assertCount(1, $attributes);
        self::assertSame(Attribute::class, $attributes[0]->getName());
    }
}
