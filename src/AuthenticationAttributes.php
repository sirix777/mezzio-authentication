<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Authentication;

enum AuthenticationAttributes: string
{
    case Context = 'sirix.authentication.context';
    case Token = 'sirix.authentication.token';
    case Actor = 'sirix.authentication.actor';
}
