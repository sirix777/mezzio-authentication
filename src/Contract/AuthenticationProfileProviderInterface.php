<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

interface AuthenticationProfileProviderInterface
{
    public function get(string $name): AuthenticationProfileInterface;

    public function getDefaultProfile(): AuthenticationProfileInterface;
}
