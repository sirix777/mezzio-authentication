<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Attribute;

use Attribute;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

final class AuthenticatedTest extends TestCase
{
    #[Test]
    public function implementsRouteAttributeModifierInterface(): void
    {
        $attribute = new Authenticated();
        self::assertInstanceOf(RouteAttributeModifierInterface::class, $attribute);
    }

    #[Test]
    public function returnsAuthenticateMiddleware(): void
    {
        $attribute = new Authenticated();
        self::assertSame([AuthenticateMiddleware::class], $attribute->getMiddleware());
    }

    #[Test]
    public function returnsEmptyDefaults(): void
    {
        $attribute = new Authenticated();
        self::assertSame([], $attribute->getDefaults());
    }

    #[Test]
    public function isAttribute(): void
    {
        $reflection = new ReflectionClass(Authenticated::class);
        $attributes = $reflection->getAttributes();

        self::assertCount(1, $attributes);
        self::assertSame(Attribute::class, $attributes[0]->getName());
    }
}
