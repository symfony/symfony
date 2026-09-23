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
 * Authenticates the client with a certificate it signed itself.
 *
 * This is the "self_signed_tls_client_auth" method of RFC 8705, Section 2.2: no certificate
 * authority is involved, the provider matching the certificate presented in the handshake
 * against the keys registered as the "jwks" of the client or served from its "jwks_uri".
 * The certificate is then only a carrier for that public key, so renewing it means
 * publishing the new key, where {@see TlsClientAuth} only asks for the same subject.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8705#section-2.2
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class SelfSignedTlsClientAuth extends AbstractTlsClientAuth
{
    public function getMethod(): string
    {
        return 'self_signed_tls_client_auth';
    }
}
