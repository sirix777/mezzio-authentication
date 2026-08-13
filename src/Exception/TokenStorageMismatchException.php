<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Exception;

use LogicException;

use function sprintf;

final class TokenStorageMismatchException extends LogicException
{
    public static function forStorage(string $expectedStorage, string $actualStorage): self
    {
        return new self(sprintf(
            'Cannot log out an authentication token from storage "%s" with a manager configured for storage "%s".',
            $actualStorage,
            $expectedStorage,
        ));
    }
}
