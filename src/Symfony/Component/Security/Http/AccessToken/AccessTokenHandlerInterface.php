<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The token handler retrieves the user identifier from the token.
 * In order to get the user identifier, implementations may need to load and validate the token (e.g. revocation, expiration time, digital signature...).
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
interface AccessTokenHandlerInterface
{
    /**
     * @throws AuthenticationException
     */
    public function getUserIdentifierFrom(string $accessToken): string;
}
