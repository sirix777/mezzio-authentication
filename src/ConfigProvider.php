<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

use Sirix\Mezzio\Authentication\Actor\GuestActor;
use Sirix\Mezzio\Authentication\Actor\NullActorProvider;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\SecurityActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Factory\ActorFactory;
use Sirix\Mezzio\Authentication\Factory\ActorProviderFactory;
use Sirix\Mezzio\Authentication\Factory\AuthenticateMiddlewareFactory;
use Sirix\Mezzio\Authentication\Factory\AuthenticatorFactory;
use Sirix\Mezzio\Authentication\Factory\AuthManagerFactory;
use Sirix\Mezzio\Authentication\Factory\OptionalAuthenticateMiddlewareFactory;
use Sirix\Mezzio\Authentication\Factory\SecurityActorProviderFactory;
use Sirix\Mezzio\Authentication\Factory\SessionTokenStorageFactory;
use Sirix\Mezzio\Authentication\Factory\TokenStorageProviderFactory;
use Sirix\Mezzio\Authentication\Factory\TokenTransportFactory;
use Sirix\Mezzio\Authentication\Middleware\AuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Middleware\GuestOnlyMiddleware;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;

final readonly class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                AuthManagerInterface::class => AuthManagerFactory::class,
                AuthenticatorInterface::class => AuthenticatorFactory::class,
                AuthActorProviderInterface::class => ActorProviderFactory::class,
                SecurityActorProviderInterface::class => SecurityActorProviderFactory::class,
                ActorInterface::class => ActorFactory::class,
                TokenStorageProviderInterface::class => TokenStorageProviderFactory::class,
                SessionTokenStorage::class => SessionTokenStorageFactory::class,
                TokenTransportInterface::class => TokenTransportFactory::class,
                AuthenticateMiddleware::class => AuthenticateMiddlewareFactory::class,
                OptionalAuthenticateMiddleware::class => OptionalAuthenticateMiddlewareFactory::class,
            ],
            'invokables' => [
                AuthenticationContext::class => AuthenticationContext::class,
                NullActorProvider::class => NullActorProvider::class,
                GuestActor::class => GuestActor::class,
                NullTokenStorage::class => NullTokenStorage::class,
                BearerTokenTransport::class => BearerTokenTransport::class,
                GuestOnlyMiddleware::class => GuestOnlyMiddleware::class,
            ],
            'aliases' => [
                AuthContextInterface::class => AuthenticationContext::class,
            ],
        ];
    }
}
