<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Integration\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/integration/authenticated-unknown', name: 'integration.authenticated_unknown')]
#[Authenticated(profile: 'unknown')]
final class AuthenticatedUnknownProfileRouteHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not executed by route registration tests.');
    }
}
