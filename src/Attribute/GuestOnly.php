<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Attribute;

use Attribute;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class GuestOnly implements RouteAttributeModifierInterface
{
    public function getMiddleware(): array
    {
        return [
            OptionalAuthenticateMiddleware::class,
            GuestOnlyMiddleware::class,
        ];
    }

    public function getDefaults(): array
    {
        return [];
    }
}
