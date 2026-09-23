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
    public function testPresentsTheCertificateOnTheRequest()
    {
        $clientAuthentication = new SelfSignedTlsClientAuth('/certs/self-signed.pem', '/certs/self-signed.key', 'secret-passphrase');

        $options = $clientAuthentication->authenticate('test-client-id', 'https://mtls.provider.example.com/token', ['body' => ['grant_type' => 'authorization_code']]);

        $this->assertSame('/certs/self-signed.pem', $options['local_cert']);
        $this->assertSame('/certs/self-signed.key', $options['local_pk']);
        $this->assertSame('secret-passphrase', $options['passphrase']);
        $this->assertSame(['grant_type' => 'authorization_code'], $options['body']);
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
        $this->assertSame('self_signed_tls_client_auth', (new SelfSignedTlsClientAuth('/certs/self-signed.pem'))->getMethod());
    }

    public function testRejectsAnEmptyCertificate()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The certificate of the "self_signed_tls_client_auth" OAuth2 client authentication cannot be empty');

        new SelfSignedTlsClientAuth('');
    }
}
