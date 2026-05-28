<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use Sirix\Mezzio\Authentication\Actor\GuestActor;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\SecurityActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Factory\ActorFactory;
use Sirix\Mezzio\Authentication\Factory\AuthenticateMiddlewareFactory;
use Sirix\Mezzio\Authentication\Factory\AuthenticatorFactory;
use Sirix\Mezzio\Authentication\Factory\AuthManagerFactory;
use Sirix\Mezzio\Authentication\Factory\OptionalAuthenticateMiddlewareFactory;
use Sirix\Mezzio\Authentication\Factory\SecurityActorProviderFactory;
use Sirix\Mezzio\Authentication\Factory\TokenStorageProviderFactory;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;

final class StrictFactoryDependenciesTest extends TestCase
{
    /**
     * @param Closure(ArrayContainer): mixed $factory
     * @param array<string, class-string>    $registeredStubs
     */
    #[Test]
    #[DataProvider('factoriesWithRequiredContainerServices')]
    public function throwsPackageExceptionWhenRequiredContainerServiceIsMissing(
        Closure $factory,
        array $registeredStubs,
        string $missingServiceId,
    ): void {
        $services = [];
        foreach ($registeredStubs as $serviceId => $stubClass) {
            $services[$serviceId] = $this->createStub($stubClass);
        }

        $this->expectException(MissingContainerServiceException::class);
        $this->expectExceptionMessage($missingServiceId);

        $factory(new ArrayContainer($services));
    }

    /**
     * @return iterable<string, array{Closure(ArrayContainer): mixed, array<string, class-string>, string}>
     */
    public static function factoriesWithRequiredContainerServices(): iterable
    {
        yield 'actor factory' => [
            static fn (ArrayContainer $arrayContainer): mixed => (new ActorFactory())($arrayContainer),
            [],
            SecurityActorProviderInterface::class,
        ];

        yield 'auth manager factory' => [
            static fn (ArrayContainer $arrayContainer): mixed => (new AuthManagerFactory())($arrayContainer),
            [TokenStorageProviderInterface::class => TokenStorageProviderInterface::class],
            TokenTransportInterface::class,
        ];

        yield 'authenticate middleware factory' => [
            static fn (ArrayContainer $arrayContainer): mixed => (new AuthenticateMiddlewareFactory())($arrayContainer),
            [
                AuthenticatorInterface::class => AuthenticatorInterface::class,
                TokenStorageProviderInterface::class => TokenStorageProviderInterface::class,
            ],
            TokenTransportInterface::class,
        ];

        yield 'authenticator factory' => [
            static fn (ArrayContainer $arrayContainer): mixed => (new AuthenticatorFactory())($arrayContainer),
            [],
            AuthActorProviderInterface::class,
        ];

        yield 'optional authenticate middleware factory' => [
            static fn (ArrayContainer $arrayContainer): mixed => (new OptionalAuthenticateMiddlewareFactory())($arrayContainer),
            [
                AuthenticatorInterface::class => AuthenticatorInterface::class,
                TokenStorageProviderInterface::class => TokenStorageProviderInterface::class,
            ],
            TokenTransportInterface::class,
        ];

        yield 'security actor provider factory' => [
            static fn (ArrayContainer $arrayContainer): mixed => (new SecurityActorProviderFactory())($arrayContainer),
            [AuthContextInterface::class => AuthContextInterface::class],
            GuestActor::class,
        ];

        yield 'token storage provider factory' => [
            static fn (ArrayContainer $arrayContainer): mixed => (new TokenStorageProviderFactory())($arrayContainer),
            [],
            NullTokenStorage::class,
        ];
    }
}
