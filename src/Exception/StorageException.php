<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Exception;

use RuntimeException;
use Throwable;

final class StorageException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        if ('' === $message) {
            $message = 'An error occurred with the token storage.';
        }

        parent::__construct($message, $code, $previous);
    }
}
