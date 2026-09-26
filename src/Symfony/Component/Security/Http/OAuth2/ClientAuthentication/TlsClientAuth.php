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
 * Authenticates the client with a certificate a certificate authority issued to it.
 *
 * This is the "tls_client_auth" method of RFC 8705, Section 2.1: the provider validates the
 * certificate chain up to a trust anchor it holds, then matches a single expected subject
 * of the certificate, registered as one of the "tls_client_auth_subject_dn",
 * "tls_client_auth_san_dns", "tls_client_auth_san_uri", "tls_client_auth_san_ip" or
 * "tls_client_auth_san_email" metadata of the client. Renewing the certificate keeps the
 * client authenticated as long as that value is kept, where {@see SelfSignedTlsClientAuth}
 * requires the new public key to be registered.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8705#section-2.1
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class TlsClientAuth extends AbstractTlsClientAuth
{
    public function getMethod(): string
    {
        return 'tls_client_auth';
    }
}
