<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Integration\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Authentication\Attribute\GuestOnly;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/integration/guest-web', name: 'integration.guest_web')]
#[GuestOnly(profile: 'web')]
final class GuestOnlyProfileRouteHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Not executed by route registration tests.');
    }
}
