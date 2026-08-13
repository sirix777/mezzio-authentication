<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Factory\AuthenticationProfileProviderFactory;
use Sirix\Mezzio\Authentication\Storage\NullTokenStorage;
use Sirix\Mezzio\Authentication\Storage\SessionTokenStorage;
use Sirix\Mezzio\Authentication\TokenStorageProvider;
use Sirix\Mezzio\Authentication\Transport\BearerTokenTransport;
use Sirix\Mezzio\Authentication\Transport\CookieTokenTransport;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;
use stdClass;

final class AuthenticationProfileProviderFactoryTest extends TestCase
{
    #[Test]
    public function buildsIndependentCookieAndBearerProfilesAndSelectsTheConfiguredDefault(): void
    {
        $authenticationProfileProvider = $this->createProvider([
            'authentication' => [
                'default_profile' => 'api',
                'profiles'        => [
                    'web' => [
                        'transport'         => 'cookie',
                        'storage'           => 'session',
                        'transport_options' => [
                            'name' => 'web_auth',
                        ],
                    ],
                    'api' => [
                        'transport' => 'bearer',
                        'storage'   => 'redis',
                    ],
                ],
            ],
        ], $this->storageProvider([
            'session' => new SessionTokenStorage(),
            'redis'   => $this->storage(),
        ]));
        $authenticationProfile      = $authenticationProfileProvider->get('web');
        $api                        = $authenticationProfileProvider->get('api');
        $serverRequest              = (new Psr7Factory())
            ->createServerRequest('GET', '/')
            ->withCookieParams([
                'web_auth' => 'web-token',
            ])
            ->withHeader('Authorization', 'Bearer api-token')
        ;

        self::assertSame('session', $authenticationProfile->storageName());
        self::assertSame('redis', $api->storageName());
        self::assertSame('web-token', $authenticationProfile->transport()->fetch($serverRequest));
        self::assertSame('api-token', $api->transport()->fetch($serverRequest));
        self::assertSame($api, $authenticationProfileProvider->getDefaultProfile());
        self::assertNotSame($authenticationProfile->transport(), $api->transport());
    }

    #[Test]
    public function buildsTheLegacyBearerAndNullProfileWhenNoProfilesAreConfigured(): void
    {
        $authenticationProfileProvider = $this->createProvider([], $this->storageProvider());
        $serverRequest                 = (new Psr7Factory())
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Bearer legacy-token')
        ;

        self::assertSame('legacy', $authenticationProfileProvider->getDefaultProfile()->name());
        self::assertSame('null', $authenticationProfileProvider->getDefaultProfile()->storageName());
        self::assertSame('legacy-token', $authenticationProfileProvider->getDefaultProfile()->transport()->fetch($serverRequest));
    }

    #[Test]
    public function validatesReservedTransportMappingsWhenNoProfilesAreConfigured(): void
    {
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.transports.bearer');

        $this->createProvider([
            'authentication' => [
                'transports' => [
                    'bearer' => 'app.transport.override',
                ],
            ],
        ], $this->storageProvider());
    }

    #[Test]
    public function buildsTheLegacyProfileFromExistingConfiguration(): void
    {
        $authenticationProfileProvider = $this->createProvider([
            'authentication' => [
                'default_storage' => 'session',
                'transport'       => [
                    'driver'  => 'cookie',
                    'storage' => 'session',
                ],
                'cookie'          => [
                    'name' => 'legacy_auth',
                ],
            ],
        ], $this->storageProvider([
            'session' => new SessionTokenStorage(),
        ]), [
            TokenTransportInterface::class => new CookieTokenTransport('legacy_auth'),
        ]);

        self::assertSame('session', $authenticationProfileProvider->getDefaultProfile()->storageName());
        self::assertSame('legacy-token', $authenticationProfileProvider->getDefaultProfile()->transport()->fetch(
            (new Psr7Factory())->createServerRequest('GET', '/')->withCookieParams([
                'legacy_auth' => 'legacy-token',
            ]),
        ));
    }

    #[Test]
    public function usesTheContainerProvidedTransportForTheLegacyDefaultProfile(): void
    {
        $customTransport = $this->createStub(TokenTransportInterface::class);

        $authenticationProfileProvider = $this->createProvider([], $this->storageProvider(), [
            TokenTransportInterface::class => $customTransport,
        ]);

        self::assertSame($customTransport, $authenticationProfileProvider->getDefaultProfile()->transport());
    }

    #[Test]
    public function rejectsAnUnknownConfiguredDefaultProfile(): void
    {
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.default_profile');

        $this->createProvider([
            'authentication' => [
                'default_profile' => 'missing',
                'profiles'        => [
                    'web' => [
                        'transport' => 'bearer',
                        'storage'   => 'null',
                    ],
                ],
            ],
        ], $this->storageProvider());
    }

    #[Test]
    public function validatesUnknownStorageForEveryProfileBeforeSelectingTheDefault(): void
    {
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.api.storage');

        $this->createProvider([
            'authentication' => [
                'default_profile' => 'web',
                'profiles'        => [
                    'web' => [
                        'transport' => 'bearer',
                        'storage'   => 'null',
                    ],
                    'api' => [
                        'transport' => 'bearer',
                        'storage'   => 'redis',
                    ],
                ],
            ],
        ], $this->storageProvider());
    }

    #[Test]
    public function failsEagerlyForAnInvalidUnusedProfileWithoutEchoingItsConfiguredValue(): void
    {
        try {
            $this->createProvider([
                'authentication' => [
                    'default_profile' => 'web',
                    'profiles'        => [
                        'web' => [
                            'transport' => 'bearer',
                            'storage'   => 'null',
                        ],
                        'api' => [
                            'transport'         => 'bearer',
                            'storage'           => 'null',
                            'transport_options' => [
                                'header' => ['Authorization secret'],
                            ],
                        ],
                    ],
                ],
            ], $this->storageProvider());
            self::fail('Expected the invalid unused profile to fail during provider construction.');
        } catch (InvalidConfigValueException $exception) {
            self::assertStringContainsString('authentication.profiles.api.transport_options.header', $exception->getMessage());
            self::assertStringNotContainsString('Authorization secret', $exception->getMessage());
            self::assertStringNotContainsString('Cookie secret', $exception->getMessage());
            self::assertStringNotContainsString('payload secret', $exception->getMessage());
        }
    }

    #[Test]
    public function preservesTheMissingSessionStorageDiagnosticForAProfile(): void
    {
        $this->expectException(MissingContainerServiceException::class);
        $this->expectExceptionMessage(SessionTokenStorage::class);
        $this->expectExceptionMessage('authentication.profiles.web.storage');

        $this->createProvider([
            'authentication' => [
                'profiles' => [
                    'web' => [
                        'transport' => 'cookie',
                        'storage'   => 'session',
                    ],
                ],
            ],
        ], $this->storageProvider());
    }

    #[Test]
    public function rejectsCustomTransportServicesWithAnInvalidType(): void
    {
        $this->expectException(InvalidContainerServiceException::class);

        $this->createProvider([
            'authentication' => [
                'profiles'   => [
                    'api' => [
                        'transport' => 'signed',
                        'storage'   => 'null',
                    ],
                ],
                'transports' => [
                    'signed' => 'app.transport.signed',
                ],
            ],
        ], $this->storageProvider(), [
            'app.transport.signed' => new stdClass(),
        ]);
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $services
     */
    private function createProvider(
        array $configuration,
        TokenStorageProviderInterface $tokenStorageProvider,
        array $services = [],
    ): AuthenticationProfileProviderInterface {
        return (new AuthenticationProfileProviderFactory())(new ArrayContainer([
            'config'                             => $configuration,
            TokenStorageProviderInterface::class => $tokenStorageProvider,
            AuthenticatorInterface::class        => $this->createStub(AuthenticatorInterface::class),
            TokenTransportInterface::class       => new BearerTokenTransport(),
            ...$services,
        ]));
    }

    /**
     * @param array<string, TokenStorageInterface> $storages
     */
    private function storageProvider(array $storages = []): TokenStorageProviderInterface
    {
        return new TokenStorageProvider('null', [
            'null' => new NullTokenStorage(),
            ...$storages,
        ]);
    }

    private function storage(): TokenStorageInterface
    {
        return new class implements TokenStorageInterface {
            public function create(array $payload, ?int $expiresAt = null, ?ServerRequestInterface $serverRequest = null): TokenInterface
            {
                throw new LogicException('Not needed for this test.');
            }

            public function load(string $id, ?ServerRequestInterface $serverRequest = null): ?TokenInterface
            {
                return null;
            }

            public function delete(TokenInterface $token, ?ServerRequestInterface $serverRequest = null): void {}
        };
    }
}
