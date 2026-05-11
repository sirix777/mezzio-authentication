<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

use RuntimeException;

final class AuthenticationException extends RuntimeException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $message = 'Unauthorized',
        private readonly array $headers = [],
        private readonly ?string $publicMessage = null,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 401;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getPublicMessage(): string
    {
        return $this->publicMessage ?? $this->getMessage();
    }
}
