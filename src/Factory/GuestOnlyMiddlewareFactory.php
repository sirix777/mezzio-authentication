<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;

final class GuestOnlyMiddlewareFactory
{
    public function __invoke(): GuestOnlyMiddleware
    {
        return new GuestOnlyMiddleware();
    }
}
