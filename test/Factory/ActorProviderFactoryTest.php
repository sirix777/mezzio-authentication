<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Factory\ActorProviderFactory;
use Sirix\Mezzio\Authentication\Token\AuthToken;
use SirixTest\Mezzio\Authentication\Support\ArrayContainer;

final class ActorProviderFactoryTest extends TestCase
{
    #[Test]
    public function usesDefaultActorPayloadKeys(): void
    {
        $provider = (new ActorProviderFactory())(new ArrayContainer([
            'config' => [],
        ]));

        $actor = $provider->getActor(new AuthToken('id', 'null', [
            'roles' => ['admin'],
        ]));

        self::assertInstanceOf(ActorInterface::class, $actor);
        self::assertSame(['admin'], $actor->getRoles());
    }

    #[Test]
    public function readsActorPayloadKeysFromConfig(): void
    {
        $provider = (new ActorProviderFactory())(new ArrayContainer([
            'config' => [
                'authentication' => [
                    'actor' => [
                        'roles_key' => 'permissions',
                        'role_key' => 'permission',
                    ],
                ],
            ],
        ]));

        $actor = $provider->getActor(new AuthToken('id', 'null', [
            'permission' => 'editor',
        ]));

        self::assertInstanceOf(ActorInterface::class, $actor);
        self::assertSame(['editor'], $actor->getRoles());
    }
}
