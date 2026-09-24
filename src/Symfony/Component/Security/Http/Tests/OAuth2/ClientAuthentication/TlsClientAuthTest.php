<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\OAuth2\ClientAuthentication;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\TlsClientAuth;

class TlsClientAuthTest extends TestCase
{
    /**
     * The one method putting no credential in the request at all.
     *
     * The client proves it holds the private key of its certificate in the TLS handshake
     * that carries the request, which has happened before the provider reads any of it, and
     * the certificate itself is carried by the HTTP client: RFC 8705, Section 4 makes it a
     * property of the client rather than of this method, a client authenticating otherwise
     * being able to present one only to have its tokens bound to it.
     */
    public function testLeavesTheRequestUntouched()
    {
        $clientAuthentication = new TlsClientAuth();

        $options = $clientAuthentication->authenticate('test-client-id', 'https://mtls.provider.example.com/token', [
            'body' => ['grant_type' => 'authorization_code', 'code' => 'auth-code'],
        ]);

        $this->assertSame(['body' => ['grant_type' => 'authorization_code', 'code' => 'auth-code']], $options);
    }

    /**
     * Nothing of the client identifier travels either: the "client_id" the token request
     * already carries is what names the registration the certificate is checked against.
     */
    public function testAddsNoClientIdOfItsOwn()
    {
        $options = (new TlsClientAuth())->authenticate('test-client-id', 'https://mtls.provider.example.com/token', []);

        $this->assertSame([], $options);
    }

    public function testReportsItsMethod()
    {
        $this->assertSame('tls_client_auth', (new TlsClientAuth())->getMethod());
    }
}
