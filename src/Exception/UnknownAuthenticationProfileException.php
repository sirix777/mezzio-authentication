<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Exception;

use InvalidArgumentException;

use function implode;
use function sprintf;

final class UnknownAuthenticationProfileException extends InvalidArgumentException
{
    /**
     * @param list<string> $availableProfiles
     */
    public static function forName(string $name, array $availableProfiles): self
    {
        return new self(sprintf(
            'Authentication profile "%s" is not registered. Available profiles: %s.',
            $name,
            [] === $availableProfiles ? '(none)' : implode(', ', $availableProfiles),
        ));
    }
}
