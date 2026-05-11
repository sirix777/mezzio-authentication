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
use Sirix\Mezzio\Authentication\Storage\StorageException;

use function is_string;

abstract readonly class AbstractAuthenticateMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthenticatorInterface $authenticator,
        private TokenStorageProviderInterface $storageProvider,
        private TokenTransportInterface $transport,
        private string $storage,
    ) {}

    /**
     * @return array{0: ServerRequestInterface, 1: AuthContextInterface}
     */
    final protected function authenticate(ServerRequestInterface $request): array
    {
        $tokenId = $this->transport->fetch($request);
        $token = null;

        if (is_string($tokenId) && '' !== $tokenId) {
            try {
                $token = $this->storageProvider
                    ->getStorage($this->storage)
                    ->load($tokenId, $request)
                ;
            } catch (StorageException) {
                $token = null;
            }
        }

        $context = $this->authenticator->authenticate($token);

        return [
            $request
                ->withAttribute(AuthenticationAttributes::Context->value, $context)
                ->withAttribute(AuthenticationAttributes::Token->value, $context->token())
                ->withAttribute(AuthenticationAttributes::Actor->value, $context->actor()),
            $context,
        ];
    }
}
