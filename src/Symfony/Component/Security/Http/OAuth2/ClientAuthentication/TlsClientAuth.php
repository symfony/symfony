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
 * This is the "tls_client_auth" method of RFC 8705, Section 2.1: the provider validates the certificate chain, then matches the subject the client registered.
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
