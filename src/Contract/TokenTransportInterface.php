<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface TokenTransportInterface
{
    public function fetch(ServerRequestInterface $request): ?string;

    public function attach(ResponseInterface $response, TokenInterface $token): ResponseInterface;

    public function detach(ResponseInterface $response): ResponseInterface;
}
