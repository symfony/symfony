<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * AuthenticationExpiredException is thrown when an authentication token becomes un-authenticated between requests.
 *
 * In practice, this is due to the User changing between requests (e.g. password changes),
 * causes the token to become un-authenticated.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class AuthenticationExpiredException extends AccountStatusException
{
    public function getMessageKey(): string
    {
        return 'Authentication expired because your account information has changed.';
    }
}
