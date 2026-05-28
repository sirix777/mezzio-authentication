<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Exception\AlreadyAuthenticatedException;

final readonly class GuestOnlyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = $request->getAttribute(AuthenticationAttributes::Context->value);

        if ($context instanceof AuthContextInterface && $context->check()) {
            throw new AlreadyAuthenticatedException();
        }

        return $handler->handle($request);
    }
}
