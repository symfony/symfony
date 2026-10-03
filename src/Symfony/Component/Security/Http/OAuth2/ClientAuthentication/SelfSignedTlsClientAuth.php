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
 * This is the "self_signed_tls_client_auth" method of RFC 8705, Section 2.2: the provider matches the certificate against the keys the client registered.
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
