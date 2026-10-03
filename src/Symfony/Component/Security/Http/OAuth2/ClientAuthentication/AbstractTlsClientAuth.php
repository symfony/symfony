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
 * Authenticates the client with the certificate it presents in the TLS handshake (RFC 8705, Section 2).
 *
 * Nothing is added to the request: the provider reads the certificate from the handshake, and the "client_id" names the registration it is checked against.
 * The certificate is not held here, since a client may present one without authenticating with it (Section 4): OidcClient sends it with its requests.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8705#section-2
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
abstract class AbstractTlsClientAuth implements ClientAuthenticationInterface
{
    final public function authenticate(string $clientId, string $tokenEndpoint, array $options): array
    {
        return $options;
    }
}
