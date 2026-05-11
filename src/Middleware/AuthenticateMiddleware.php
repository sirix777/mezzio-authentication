<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationException;

final readonly class AuthenticateMiddleware extends AbstractAuthenticateMiddleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        [$request, $context] = $this->authenticate($request);

        if ($context->guest()) {
            throw new AuthenticationException();
        }

        return $handler->handle($request);
    }
}
