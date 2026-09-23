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
    public function testPresentsTheCertificateOnTheRequest()
    {
        $clientAuthentication = new TlsClientAuth('/certs/client.pem', '/certs/client.key', 'secret-passphrase');

        $options = $clientAuthentication->authenticate('test-client-id', 'https://mtls.provider.example.com/token', ['body' => ['grant_type' => 'refresh_token']]);

        $this->assertSame('/certs/client.pem', $options['local_cert']);
        $this->assertSame('/certs/client.key', $options['local_pk']);
        $this->assertSame('secret-passphrase', $options['passphrase']);
    }

    /**
     * The method authenticates in the handshake, so the request is the one of a client
     * sending no credentials at all.
     *
     * RFC 8705, Section 2 relies on the "client_id" the token request already carries to
     * name the registration the certificate is checked against, and adds nothing else.
     */
    public function testLeavesTheRequestUntouched()
    {
        $clientAuthentication = new TlsClientAuth('/certs/client.pem');

        $options = $clientAuthentication->authenticate('test-client-id', 'https://mtls.provider.example.com/token', ['body' => ['grant_type' => 'refresh_token', 'client_id' => 'test-client-id'], 'timeout' => 3]);

        $this->assertSame(['grant_type' => 'refresh_token', 'client_id' => 'test-client-id'], $options['body']);
        $this->assertSame(3, $options['timeout']);
        $this->assertArrayNotHasKey('auth_basic', $options);
    }

    /**
     * A PEM file may hold the certificate and its private key, and an unencrypted key has
     * no passphrase.
     *
     * Neither option is set then, so that the ones the HTTP client is configured with are
     * not overwritten with nothing.
     */
    public function testSetsNoKeyNorPassphraseWhenItHasNone()
    {
        $options = (new TlsClientAuth('/certs/client.pem'))->authenticate('test-client-id', 'https://mtls.provider.example.com/token', []);

        $this->assertSame(['local_cert' => '/certs/client.pem'], $options);
    }

    public function testReportsItsMethod()
    {
        $this->assertSame('tls_client_auth', (new TlsClientAuth('/certs/client.pem'))->getMethod());
    }

    public function testRejectsAnEmptyCertificate()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The certificate of the "tls_client_auth" OAuth2 client authentication cannot be empty');

        new TlsClientAuth('');
    }

    public function testRejectsAnEmptyKey()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The private key of the "tls_client_auth" OAuth2 client authentication cannot be empty');

        new TlsClientAuth('/certs/client.pem', '');
    }
}
