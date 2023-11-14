<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport\Smtp\Auth;

/**
 * The auth token provider knows how to create a valid token for the XOAuth2Authenticator.
 *
 * Usually with OAuth, you will need to do some web request to fetch the token.
 * You also want to cache the token for as long as it is valid.
 */
interface AuthTokenProviderInterface
{
    /**
     * Acquire the authentication token.
     */
    public function getToken(): string;
}
