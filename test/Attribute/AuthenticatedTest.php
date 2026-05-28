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
        $authenticated = new Authenticated();
        self::assertInstanceOf(RouteAttributeModifierInterface::class, $authenticated);
    }

    #[Test]
    public function returnsAuthenticateMiddleware(): void
    {
        $authenticated = new Authenticated();
        self::assertSame([AuthenticateMiddleware::class], $authenticated->getMiddleware());
    }

    #[Test]
    public function returnsEmptyDefaults(): void
    {
        $authenticated = new Authenticated();
        self::assertSame([], $authenticated->getDefaults());
    }

    #[Test]
    public function isAttribute(): void
    {
        $reflectionClass = new ReflectionClass(Authenticated::class);
        $attributes = $reflectionClass->getAttributes();

        self::assertCount(1, $attributes);
        self::assertSame(Attribute::class, $attributes[0]->getName());
    }
}
