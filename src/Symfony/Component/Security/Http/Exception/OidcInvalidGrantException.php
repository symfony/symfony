<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Thrown when an OIDC provider answers a token request with "invalid_grant".
 *
 * That error is the one of RFC 6749, Section 5.2.
 *
 * It is the one token endpoint error a caller has to tell apart from every other
 * failure: the grant is gone for good, where an unreachable provider or a server
 * error only means the request can be tried again.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
class OidcInvalidGrantException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'The OIDC provider rejected the grant.';
    }
}
