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
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\SelfSignedTlsClientAuth;

class SelfSignedTlsClientAuthTest extends TestCase
{
    /**
     * The certificate is presented in the handshake, before the provider reads anything of
     * the request, and it is the HTTP client that carries it.
     */
    public function testLeavesTheRequestUntouched()
    {
        $clientAuthentication = new SelfSignedTlsClientAuth();

        $options = $clientAuthentication->authenticate('test-client-id', 'https://mtls.provider.example.com/token', ['body' => ['grant_type' => 'authorization_code']]);

        $this->assertSame(['body' => ['grant_type' => 'authorization_code']], $options);
    }

    /**
     * What the provider checks the certificate against is the only difference with
     * {@see \Symfony\Component\Security\Http\OAuth2\ClientAuthentication\TlsClientAuth},
     * and it never reaches the client.
     *
     * The name the method reports is therefore the whole of it here: it is what the client
     * is registered with, and what the provider announces support for.
     */
    public function testReportsItsMethod()
    {
        $this->assertSame('self_signed_tls_client_auth', (new SelfSignedTlsClientAuth())->getMethod());
    }
}
