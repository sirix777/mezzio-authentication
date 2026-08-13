<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Factory\AuthManagerFactory;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;

final class AuthManagerFactoryTest extends TestCase
{
    #[Test]
    public function usesTransportStorage(): void
    {
        $authenticationManager = (new AuthManagerFactory())(new ArrayContainer([
            'config'                             => [
                'authentication' => [
                    'default_storage' => 'session',
                    'transport'       => [
                        'storage' => 'api',
                    ],
                ],
            ],
            TokenStorageProviderInterface::class => $this->createStub(TokenStorageProviderInterface::class),
            TokenTransportInterface::class       => $this->createStub(TokenTransportInterface::class),
        ]));

        self::assertInstanceOf(AuthManagerInterface::class, $authenticationManager);
    }
}
