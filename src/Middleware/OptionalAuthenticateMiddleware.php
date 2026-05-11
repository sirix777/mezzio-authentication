<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class OptionalAuthenticateMiddleware extends AbstractAuthenticateMiddleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        [$request] = $this->authenticate($request);

        return $handler->handle($request);
    }
}
