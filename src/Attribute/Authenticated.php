<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Attribute;

use Attribute;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Authenticated implements RouteAttributeModifierInterface
{
    public function getMiddleware(): array
    {
        return [AuthenticateMiddleware::class];
    }

    public function getDefaults(): array
    {
        return [];
    }
}
