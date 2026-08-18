<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;

final readonly class AuthenticationProfile implements AuthenticationProfileInterface
{
    public function __construct(
        private string $name,
        private string $storageName,
        private TokenTransportInterface $tokenTransport,
        private AuthManagerInterface $authManager,
        private AuthenticateMiddleware $authenticateMiddleware,
        private OptionalAuthenticateMiddleware $optionalAuthenticateMiddleware,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function storageName(): string
    {
        return $this->storageName;
    }

    public function transport(): TokenTransportInterface
    {
        return $this->tokenTransport;
    }

    public function manager(): AuthManagerInterface
    {
        return $this->authManager;
    }

    public function authenticateMiddleware(): AuthenticateMiddleware
    {
        return $this->authenticateMiddleware;
    }

    public function optionalAuthenticateMiddleware(): OptionalAuthenticateMiddleware
    {
        return $this->optionalAuthenticateMiddleware;
    }
}
