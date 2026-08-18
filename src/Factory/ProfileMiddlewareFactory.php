<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Factory;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

use function array_key_exists;
use function is_string;
use function sprintf;

final readonly class ProfileMiddlewareFactory implements MiddlewareFactoryInterface
{
    public function create(ContainerInterface $container, MiddlewareSpecification $specification): MiddlewareInterface
    {
        if (! array_key_exists('profile', $specification->arguments)) {
            throw new InvalidMiddlewareSpecificationException(
                'Profile middleware specification is missing the required "profile" argument.',
            );
        }

        $profile = $specification->arguments['profile'];

        if (! is_string($profile) || '' === $profile) {
            throw new InvalidMiddlewareSpecificationException(
                'Profile middleware specification requires a non-empty-string "profile" argument.',
            );
        }

        $authenticationProfile = $container->get(AuthenticationProfileProviderInterface::class)
            ->get($profile)
        ;

        return match ($specification->service) {
            AuthenticateMiddleware::class         => $authenticationProfile->authenticateMiddleware(),
            OptionalAuthenticateMiddleware::class => $authenticationProfile->optionalAuthenticateMiddleware(),
            default                               => throw new InvalidMiddlewareSpecificationException(
                sprintf(
                    'Profile middleware specification service "%s" is not supported.',
                    $specification->service,
                ),
            ),
        };
    }
}
