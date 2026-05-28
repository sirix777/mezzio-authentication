<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticatorInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Exception\StorageException;

use function is_string;

abstract readonly class AbstractAuthenticateMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthenticatorInterface $authenticator,
        private TokenStorageProviderInterface $tokenStorageProvider,
        private TokenTransportInterface $tokenTransport,
        private string $storage,
    ) {}

    /**
     * @return array{0: ServerRequestInterface, 1: AuthContextInterface}
     */
    final protected function authenticate(ServerRequestInterface $serverRequest): array
    {
        $tokenId = $this->tokenTransport->fetch($serverRequest);
        $token = null;

        if (is_string($tokenId) && '' !== $tokenId) {
            try {
                $token = $this->tokenStorageProvider
                    ->getStorage($this->storage)
                    ->load($tokenId, $serverRequest)
                ;
            } catch (StorageException) {
                $token = null;
            }
        }

        $authContext = $this->authenticator->authenticate($token);

        return [
            $serverRequest
                ->withAttribute(AuthenticationAttributes::Context->value, $authContext)
                ->withAttribute(AuthenticationAttributes::Token->value, $authContext->token())
                ->withAttribute(AuthenticationAttributes::Actor->value, $authContext->actor()),
            $authContext,
        ];
    }
}
