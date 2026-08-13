<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Exception\UnknownAuthenticationProfileException;

use function array_keys;

final readonly class AuthenticationProfileProvider implements AuthenticationProfileProviderInterface
{
    /**
     * @param array<string, AuthenticationProfileInterface> $profiles
     */
    public function __construct(private array $profiles, private AuthenticationProfileInterface $authenticationProfile) {}

    public function get(string $name): AuthenticationProfileInterface
    {
        return $this->profiles[$name]
            ?? throw UnknownAuthenticationProfileException::forName($name, array_keys($this->profiles));
    }

    public function getDefaultProfile(): AuthenticationProfileInterface
    {
        return $this->authenticationProfile;
    }
}
