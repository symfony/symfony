<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\OAuth2\ClientAuthentication;

/**
 * Authenticates the client with the certificate it presents in the TLS handshake.
 *
 * This is the mutual-TLS client authentication of RFC 8705, Section 2, the only method
 * putting no credential in the request at all: the client proves it holds the private key
 * of a certificate the provider knows, in the handshake that carries the request, and the
 * "client_id" of RFC 6749, Section 2.3.1 names the registration that certificate is then
 * checked against. What it is checked against tells the two methods built on this apart,
 * {@see TlsClientAuth} matching a subject a certificate authority vouches for,
 * {@see SelfSignedTlsClientAuth} a public key the client registered itself.
 *
 * The certificate itself is not held here: Section 4 makes mutual-TLS client authentication
 * and certificate-bound access tokens independent of each other, a public client with no
 * credentials at all being able to present one only so that its tokens are bound to it. It
 * is therefore a property of the client and not of the method, configured once and carried
 * by the HTTP client every request to the provider is made with, which is also what decides
 * the endpoints of Section 5 are to be used. Reporting one of these two methods only tells
 * the provider to authenticate the client on that certificate.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8705#section-2 Mutual-TLS client authentication
 * @see https://datatracker.ietf.org/doc/html/rfc8705#section-4 Public clients and certificate-bound tokens
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
abstract class AbstractTlsClientAuth implements ClientAuthenticationInterface
{
    /**
     * Leaves the request untouched, credentials and body alike.
     *
     * Nothing of this method travels in the request: the handshake has happened before the
     * provider gets to read any of it.
     */
    final public function authenticate(string $clientId, string $tokenEndpoint, array $options): array
    {
        return $options;
    }
}
