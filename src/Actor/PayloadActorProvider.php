<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Actor;

use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

use function array_filter;
use function array_is_list;
use function array_map;
use function array_values;
use function is_array;
use function is_string;

final readonly class PayloadActorProvider implements AuthActorProviderInterface
{
    public function __construct(private string $rolesKey = 'roles', private ?string $roleKey = 'role') {}

    public function getActor(TokenInterface $token): ActorInterface
    {
        $payload = $token->getPayload();
        $roles = $this->extractRoles($payload);

        return new TokenActor($roles);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function extractRoles(array $payload): array
    {
        $roles = $payload[$this->rolesKey] ?? [];

        if (! is_array($roles) || ! array_is_list($roles)) {
            $roles = [];
        }

        $normalized = array_values(
            array_filter(
                array_map(
                    static fn (mixed $role): ?string => is_string($role)
                    && '' !== $role
                        ? $role
                        : null,
                    $roles,
                ),
                static fn (?string $role): bool => null !== $role,
            ),
        );

        if ([] !== $normalized || null === $this->roleKey) {
            return $normalized;
        }

        $singleRole = $payload[$this->roleKey] ?? null;

        return is_string($singleRole) && '' !== $singleRole
            ? [$singleRole]
            : [];
    }
}
