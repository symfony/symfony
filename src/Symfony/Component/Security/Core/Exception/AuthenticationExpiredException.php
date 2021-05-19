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
 * AuthenticationExpiredException is thrown when an authenticated token becomes un-authenticated between requests.
 *
 * In practice, this is due to the User changing between requests (e.g. password changes),
 * causes the token to become un-authenticated.
 */
class AuthenticationExpiredException extends AccountStatusException
{
    /**
     * {@inheritdoc}
     */
    public function getMessageKey()
    {
        return 'Authentication expired because your account information has changed.';
    }
}
