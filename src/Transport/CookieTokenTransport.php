<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Transport;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;

use function gmdate;
use function is_string;
use function rawurlencode;
use function sprintf;

final readonly class CookieTokenTransport implements TokenTransportInterface
{
    public function __construct(
        private string $name = 'mezzio_authentication',
        private string $path = '/',
        private ?string $domain = null,
        private bool $secure = false,
        private bool $httpOnly = true,
        private string $sameSite = 'Lax',
    ) {}

    public function fetch(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        $token = $cookies[$this->name] ?? null;

        return is_string($token) && '' !== $token ? $token : null;
    }

    public function attach(ResponseInterface $response, TokenInterface $token): ResponseInterface
    {
        return $response->withAddedHeader(
            'Set-Cookie',
            $this->buildCookieHeader(
                rawurlencode($token->getId()),
                $token->getExpiresAt(),
            ),
        );
    }

    public function detach(ResponseInterface $response): ResponseInterface
    {
        return $response->withAddedHeader(
            'Set-Cookie',
            $this->buildCookieHeader('deleted', 1),
        );
    }

    private function buildCookieHeader(string $value, ?int $expiresAt): string
    {
        $header = sprintf('%s=%s; Path=%s', $this->name, $value, $this->path);

        if (null !== $this->domain && '' !== $this->domain) {
            $header .= '; Domain=' . $this->domain;
        }

        if (null !== $expiresAt) {
            $header
                .= '; Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $expiresAt);
        }

        if ($this->secure) {
            $header .= '; Secure';
        }

        if ($this->httpOnly) {
            $header .= '; HttpOnly';
        }

        return $header . '; SameSite=' . $this->sameSite;
    }
}
