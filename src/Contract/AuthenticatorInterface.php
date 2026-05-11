<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Contract;

interface AuthenticatorInterface
{
    public function authenticate(?TokenInterface $token): AuthContextInterface;
}
