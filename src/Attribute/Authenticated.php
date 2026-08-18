<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Attribute;

use Attribute;
use Sirix\Mezzio\Authentication\Factory\ProfileMiddlewareFactory;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Authenticated implements RouteAttributeModifierInterface
{
    public function __construct(public ?string $profile = null) {}

    public function getMiddleware(): array
    {
        if (null === $this->profile) {
            return [AuthenticateMiddleware::class];
        }

        return [
            new MiddlewareSpecification(
                AuthenticateMiddleware::class,
                ProfileMiddlewareFactory::class,
                [
                    'profile' => $this->profile,
                ],
            ),
        ];
    }

    public function getDefaults(): array
    {
        return [];
    }
}
