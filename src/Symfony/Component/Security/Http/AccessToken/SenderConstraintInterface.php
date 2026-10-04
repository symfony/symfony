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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Tells whether the request that presents an access token proves possession of what the token is bound to.
 *
 * A bearer token is accepted from whoever holds it. A sender-constrained one names a key or a certificate
 * in its "cnf" claim (RFC 7800, Section 3.1), and is accepted only from a request that proves possession of
 * it: DPoP does it with a signature over the request (RFC 9449), mutual TLS with the certificate of the
 * handshake (RFC 8705, Section 3). The token handler says what the token is; this says whether the request
 * carrying it is the one it was issued to.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449#section-7.1
 */
interface SenderConstraintInterface
{
    /**
     * @param array<string, mixed> $claims The claims the token handler read from the access token, the "cnf" of
     *                                     RFC 7800, Section 3.1 among them
     *
     * @throws AuthenticationException when the request does not prove possession of what the token is bound to
     */
    public function check(Request $request, #[\SensitiveParameter] string $accessToken, array $claims): void;

    /**
     * The authentication scheme the challenge of this firewall names, and the parameters this constraint
     * adds to it.
     *
     * A resource server accepting sender-constrained tokens challenges with the scheme they are presented
     * under, so that a client is told how to come back rather than being sent to build a bearer request
     * that would be refused again.
     *
     * @param AuthenticationException|null $exception the failure being answered, null when the request carried
     *                                                no access token at all
     *
     * @return array{0: string, 1: array<string, string>} the scheme, and the parameters to add to the
     *                                                    "WWW-Authenticate" header, which override those
     *                                                    the authenticator builds
     */
    public function getChallenge(?AuthenticationException $exception): array;
}
