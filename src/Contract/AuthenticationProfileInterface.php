<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;

interface AuthenticationProfileInterface
{
    public function name(): string;

    public function storageName(): string;

    public function transport(): TokenTransportInterface;

    public function manager(): AuthManagerInterface;

    public function authenticateMiddleware(): AuthenticateMiddleware;

    public function optionalAuthenticateMiddleware(): OptionalAuthenticateMiddleware;
}
