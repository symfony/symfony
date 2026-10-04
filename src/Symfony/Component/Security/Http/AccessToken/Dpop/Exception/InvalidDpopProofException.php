<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\Dpop\Exception;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * Thrown when the DPoP proof a request carries is missing, unreadable, or not a proof made for that request.
 *
 * It is what tells the "invalid_dpop_proof" error code of RFC 9449, Section 7.1 from the "invalid_token" one:
 * the token may well be valid and the request still fail to prove possession of the key it is bound to.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449#section-7.1
 */
class InvalidDpopProofException extends BadCredentialsException
{
}
