<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\OAuth2\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * This exception is thrown when the user is invalid on the OIDC server (e.g.: "email" property is not in the scope).
 *
 * @experimental
 */
class InvalidClaimException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'Inactive access token.';
    }
}
