<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\AuthenticationProfileProvider;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileInterface;
use Sirix\Mezzio\Authentication\Exception\UnknownAuthenticationProfileException;

final class AuthenticationProfileProviderTest extends TestCase
{
    #[Test]
    public function returnsProfilesByTheirExactNameAndKeepsTheConfiguredDefault(): void
    {
        $web = $this->createStub(AuthenticationProfileInterface::class);
        $api = $this->createStub(AuthenticationProfileInterface::class);

        $authenticationProfileProvider = new AuthenticationProfileProvider([
            'web' => $web,
            'api' => $api,
        ], $api);

        self::assertSame($web, $authenticationProfileProvider->get('web'));
        self::assertSame($api, $authenticationProfileProvider->get('api'));
        self::assertSame($api, $authenticationProfileProvider->getDefaultProfile());
    }

    #[Test]
    public function rejectsAnUnknownProfileWithSafeAvailableNameContext(): void
    {
        $authenticationProfileProvider = new AuthenticationProfileProvider([
            'web' => $this->createStub(AuthenticationProfileInterface::class),
        ], $this->createStub(AuthenticationProfileInterface::class));

        $this->expectException(UnknownAuthenticationProfileException::class);
        $this->expectExceptionMessage('missing');
        $this->expectExceptionMessage('web');

        $authenticationProfileProvider->get('missing');
    }
}
