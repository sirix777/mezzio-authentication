<?php

declare(strict_types=1);

namespace SirixTest\Mezzio\Authentication\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Exception\AlreadyAuthenticatedException;
use Sirix\Mezzio\Authentication\Exception\AuthenticationException;
use Sirix\Mezzio\Authentication\Exception\StorageException;

final class HttpExceptionsTest extends TestCase
{
    #[Test]
    public function authenticationExceptionDescribesA401Response(): void
    {
        $default = new AuthenticationException();
        $custom  = new AuthenticationException('Token expired at 12:00', [
            'WWW-Authenticate' => 'Bearer',
        ], 'Unauthorized');

        self::assertSame(401, $default->getStatusCode());
        self::assertSame([], $default->getHeaders());
        self::assertSame('Unauthorized', $default->getPublicMessage());
        self::assertSame([
            'WWW-Authenticate' => 'Bearer',
        ], $custom->getHeaders());
        self::assertSame('Unauthorized', $custom->getPublicMessage());
        self::assertSame('Token expired at 12:00', $custom->getMessage());
        // Without an explicit public message the internal one is exposed.
        self::assertSame('Token expired at 12:00', (new AuthenticationException('Token expired at 12:00'))->getPublicMessage());
    }

    #[Test]
    public function alreadyAuthenticatedExceptionDescribesA403Response(): void
    {
        $default = new AlreadyAuthenticatedException();
        $custom  = new AlreadyAuthenticatedException('Guest-only route', [
            'X-Reason' => 'guest',
        ], 'Forbidden');

        self::assertSame(403, $default->getStatusCode());
        self::assertSame([], $default->getHeaders());
        self::assertSame('Forbidden', $default->getPublicMessage());
        self::assertSame([
            'X-Reason' => 'guest',
        ], $custom->getHeaders());
        self::assertSame('Forbidden', $custom->getPublicMessage());
    }

    #[Test]
    public function storageExceptionHasADefaultMessage(): void
    {
        self::assertSame('An error occurred with the token storage.', (new StorageException())->getMessage());
        self::assertSame('custom', (new StorageException('custom'))->getMessage());
    }
}
