<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Integration\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/integration/authenticated-api', name: 'integration.authenticated_api')]
#[Authenticated(profile: 'api')]
final class AuthenticatedProfileRouteHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not executed by route registration tests.');
    }
}
