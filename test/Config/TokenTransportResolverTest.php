<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use Sirix\Mezzio\Authentication\Config\AuthenticationProfileConfiguration;
use Sirix\Mezzio\Authentication\Config\TokenTransportResolver;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Token\AuthToken;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;
use SirixTest\Mezzio\Authentication\Support\Psr7Factory;
use stdClass;

final class TokenTransportResolverTest extends TestCase
{
    #[Test]
    public function profileCookieOptionsOverrideGlobalOptionsWithoutLeakingBetweenProfiles(): void
    {
        $configuration = [
            'authentication' => [
                'cookie'   => [
                    'path'      => '/global',
                    'http_only' => true,
                ],
                'profiles' => [
                    'web'   => [
                        'transport'         => 'cookie',
                        'storage'           => 'session',
                        'transport_options' => [
                            'name' => 'web_auth',
                        ],
                    ],
                    'admin' => [
                        'transport'         => 'cookie',
                        'storage'           => 'session',
                        'transport_options' => [
                            'name' => 'admin_auth',
                            'path' => '/admin',
                        ],
                    ],
                ],
            ],
        ];
        [$profileConfiguration, $resolver]  = $this->resolver($configuration);
        $profiles                           = $profileConfiguration->profiles();
        $psr7Factory                        = new Psr7Factory();

        $webResponse = $resolver->profileTransport($profiles['web'])->attach(
            $psr7Factory->createResponse(),
            new AuthToken('token', 'session', []),
        );
        $adminResponse = $resolver->profileTransport($profiles['admin'])->attach(
            $psr7Factory->createResponse(),
            new AuthToken('token', 'session', []),
        );

        self::assertStringContainsString('web_auth=token; Path=/global', $webResponse->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('admin_auth=token; Path=/admin', $adminResponse->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('HttpOnly', $webResponse->getHeaderLine('Set-Cookie'));
    }

    #[Test]
    public function profileBearerOptionsOverrideGlobalOptionsWithoutLeakingBetweenProfiles(): void
    {
        $configuration = [
            'authentication' => [
                'bearer'   => [
                    'scheme' => 'Token',
                ],
                'profiles' => [
                    'api'     => [
                        'transport'         => 'bearer',
                        'storage'           => 'redis',
                        'transport_options' => [
                            'header' => 'X-API-Authorization',
                        ],
                    ],
                    'partner' => [
                        'transport'         => 'bearer',
                        'storage'           => 'redis',
                        'transport_options' => [
                            'header' => 'X-Partner-Authorization',
                            'scheme' => 'Bearer',
                        ],
                    ],
                ],
            ],
        ];
        [$profileConfiguration, $resolver]  = $this->resolver($configuration);
        $profiles                           = $profileConfiguration->profiles();
        $serverRequest                      = (new Psr7Factory())
            ->createServerRequest('GET', '/')
            ->withHeader('X-API-Authorization', 'Token api-token')
            ->withHeader('X-Partner-Authorization', 'Bearer partner-token')
        ;

        self::assertSame('api-token', $resolver->profileTransport($profiles['api'])->fetch($serverRequest));
        self::assertSame('partner-token', $resolver->profileTransport($profiles['partner'])->fetch($serverRequest));
    }

    #[Test]
    public function resolvesCustomProfileTransportFromTheContainer(): void
    {
        $customTransport                   = $this->createStub(TokenTransportInterface::class);
        [$profileConfiguration, $resolver] = $this->resolver([
            'authentication' => [
                'transports' => [
                    'signed' => 'app.transport.signed',
                ],
                'profiles'   => [
                    'api' => [
                        'transport' => 'signed',
                        'storage'   => 'redis',
                    ],
                ],
            ],
        ], [
            'app.transport.signed' => $customTransport,
        ]);

        self::assertSame($customTransport, $resolver->profileTransport($profileConfiguration->profiles()['api']));
    }

    #[Test]
    public function rejectsMalformedProfileDefinitionsWithTheirFullConfigurationPath(): void
    {
        [$profileConfiguration] = $this->resolver([
            'authentication' => [
                'profiles' => [
                    'api' => [
                        'transport'         => 'bearer',
                        'storage'           => 'redis',
                        'transport_options' => 'invalid',
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.api.transport_options');

        $profileConfiguration->profiles();
    }

    #[Test]
    public function rejectsMissingProfileTransportAndStorage(): void
    {
        [$profileConfiguration] = $this->resolver([
            'authentication' => [
                'profiles' => [
                    'api' => [],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.api.transport');

        $profileConfiguration->profiles();
    }

    #[Test]
    public function rejectsAnUnknownProfileDriver(): void
    {
        [$profileConfiguration, $resolver] = $this->resolver([
            'authentication' => [
                'profiles' => [
                    'api' => [
                        'transport' => 'unknown',
                        'storage'   => 'redis',
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.api.transport');

        $resolver->profileTransport($profileConfiguration->profiles()['api']);
    }

    #[Test]
    public function rejectsProfileOptionsForCustomTransports(): void
    {
        [$profileConfiguration, $resolver] = $this->resolver([
            'authentication' => [
                'transports' => [
                    'signed' => 'app.transport.signed',
                ],
                'profiles'   => [
                    'api' => [
                        'transport'         => 'signed',
                        'storage'           => 'redis',
                        'transport_options' => [],
                    ],
                ],
            ],
        ], [
            'app.transport.signed' => $this->createStub(TokenTransportInterface::class),
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.api.transport_options');

        $resolver->profileTransport($profileConfiguration->profiles()['api']);
    }

    #[Test]
    public function rejectsUnsupportedAndWronglyTypedProfileOptions(): void
    {
        [$profileConfiguration, $resolver] = $this->resolver([
            'authentication' => [
                'profiles' => [
                    'api' => [
                        'transport'         => 'bearer',
                        'storage'           => 'redis',
                        'transport_options' => [
                            'header' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.api.transport_options.header');

        $resolver->profileTransport($profileConfiguration->profiles()['api']);
    }

    #[Test]
    public function rejectsAnUnsupportedProfileOption(): void
    {
        [$profileConfiguration, $resolver] = $this->resolver([
            'authentication' => [
                'profiles' => [
                    'api' => [
                        'transport'         => 'bearer',
                        'storage'           => 'redis',
                        'transport_options' => [
                            'unknown' => 'value',
                        ],
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.api.transport_options.unknown');

        $resolver->profileTransport($profileConfiguration->profiles()['api']);
    }

    #[Test]
    public function rejectsEmptyProfileNames(): void
    {
        [$profileConfiguration] = $this->resolver([
            'authentication' => [
                'profiles' => [
                    '' => 'invalid',
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.');

        $profileConfiguration->profiles();
    }

    #[Test]
    public function rejectsNonMapProfileDefinitions(): void
    {
        [$profileConfiguration] = $this->resolver([
            'authentication' => [
                'profiles' => [
                    'api' => 'invalid',
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.api');

        $profileConfiguration->profiles();
    }

    #[Test]
    public function rejectsEmptyProfileTransport(): void
    {
        [$profileConfiguration] = $this->resolver([
            'authentication' => [
                'profiles' => [
                    'api' => [
                        'transport' => '',
                        'storage'   => '',
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.api.transport');

        $profileConfiguration->profiles();
    }

    #[Test]
    public function rejectsEmptyProfileStorage(): void
    {
        [$profileConfiguration] = $this->resolver([
            'authentication' => [
                'profiles' => [
                    'api' => [
                        'transport' => 'bearer',
                        'storage'   => '',
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.profiles.api.storage');

        $profileConfiguration->profiles();
    }

    #[Test]
    public function rejectsReservedAndInvalidCustomTransportMappings(): void
    {
        [$profileConfiguration] = $this->resolver([
            'authentication' => [
                'transports' => [
                    'bearer' => 'app.transport.override',
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.transports.bearer');

        $profileConfiguration->customTransportServiceIds();
    }

    #[Test]
    public function rejectsEmptyAndNonStringCustomTransportServiceIds(): void
    {
        [$profileConfiguration] = $this->resolver([
            'authentication' => [
                'transports' => [
                    'signed' => '',
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.transports.signed');

        $profileConfiguration->customTransportServiceIds();
    }

    #[Test]
    public function rejectsNonStringCustomTransportServiceIds(): void
    {
        [$profileConfiguration] = $this->resolver([
            'authentication' => [
                'transports' => [
                    'signed' => false,
                ],
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.transports.signed');

        $profileConfiguration->customTransportServiceIds();
    }

    #[Test]
    public function rejectsMissingAndInvalidCustomTransportServices(): void
    {
        [$profileConfiguration, $resolver] = $this->resolver([
            'authentication' => [
                'transports' => [
                    'signed' => 'app.transport.signed',
                ],
                'profiles'   => [
                    'api' => [
                        'transport' => 'signed',
                        'storage'   => 'redis',
                    ],
                ],
            ],
        ], [
            'app.transport.signed' => new stdClass(),
        ]);

        $this->expectException(InvalidContainerServiceException::class);
        $this->expectExceptionMessage('authentication.profiles.api.transport');
        $resolver->profileTransport($profileConfiguration->profiles()['api']);
    }

    #[Test]
    public function rejectsAMissingCustomTransportService(): void
    {
        [$profileConfiguration, $resolver] = $this->resolver([
            'authentication' => [
                'transports' => [
                    'signed' => 'app.transport.signed',
                ],
                'profiles'   => [
                    'api' => [
                        'transport' => 'signed',
                        'storage'   => 'redis',
                    ],
                ],
            ],
        ]);

        $this->expectException(MissingContainerServiceException::class);
        $this->expectExceptionMessage('authentication.profiles.api.transport');
        $resolver->profileTransport($profileConfiguration->profiles()['api']);
    }

    #[Test]
    public function rejectsAnInvalidDefaultProfileName(): void
    {
        [$profileConfiguration] = $this->resolver([
            'authentication' => [
                'default_profile' => '',
            ],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('authentication.default_profile');

        $profileConfiguration->defaultProfileName();
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $services
     *
     * @return array{AuthenticationProfileConfiguration, TokenTransportResolver}
     */
    private function resolver(array $configuration, array $services = []): array
    {
        $arrayContainer = new ArrayContainer([
            'config' => $configuration,
            ...$services,
        ]);
        $containerResolver                     = ContainerResolver::forContext($arrayContainer, self::class);
        $configReader                          = ConfigReader::fromContainer($containerResolver);
        $authenticationProfileConfiguration    = new AuthenticationProfileConfiguration($configReader);

        return [
            $authenticationProfileConfiguration,
            new TokenTransportResolver($arrayContainer, $configReader, $authenticationProfileConfiguration),
        ];
    }
}
