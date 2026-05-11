<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Transport;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;

use function preg_match;
use function preg_quote;
use function trim;

final readonly class BearerTokenTransport implements TokenTransportInterface
{
    public function __construct(private string $header = 'Authorization', private string $scheme = 'Bearer') {}

    public function fetch(ServerRequestInterface $request): ?string
    {
        $header = trim($request->getHeaderLine($this->header));
        if ('' === $header) {
            return null;
        }

        $pattern = '/^' . preg_quote($this->scheme, '/') . '\s+(.+)$/i';
        if (1 !== preg_match($pattern, $header, $matches)) {
            return null;
        }

        $token = trim($matches[1] ?? '');

        return '' === $token ? null : $token;
    }

    public function attach(ResponseInterface $response, TokenInterface $token): ResponseInterface
    {
        return $response->withHeader(
            $this->header,
            $this->scheme . ' ' . $token->getId(),
        );
    }

    public function detach(ResponseInterface $response): ResponseInterface
    {
        return $response->withoutHeader($this->header);
    }
}
