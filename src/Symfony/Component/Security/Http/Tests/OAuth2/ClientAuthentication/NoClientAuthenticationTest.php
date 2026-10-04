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
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\NoClientAuthentication;

class NoClientAuthenticationTest extends TestCase
{
    public function testSendsNoCredentialAtAll()
    {
        $options = (new NoClientAuthentication())->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => ['grant_type' => 'authorization_code', 'code_verifier' => 'my-code-verifier']]);

        $this->assertSame('my-code-verifier', $options['body']['code_verifier']);
        $this->assertArrayNotHasKey('client_secret', $options['body']);
        $this->assertArrayNotHasKey('auth_basic', $options);
    }

    /**
     * Without a secret and without PKCE, nothing binds the authorization code to this client.
     *
     * An intercepted code could then be redeemed by anyone.
     */
    public function testRefusesToExchangeACodeWithoutPkce()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must exchange the authorization code with PKCE');

        (new NoClientAuthentication())->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => ['grant_type' => 'authorization_code']]);
    }

    /**
     * An empty verifier would make the exchange look protected while it is not.
     */
    public function testRefusesAnEmptyCodeVerifier()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must exchange the authorization code with PKCE');

        (new NoClientAuthentication())->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => ['grant_type' => 'authorization_code', 'code_verifier' => '']]);
    }

    /**
     * PKCE binds the authorization code to this client.
     *
     * The refresh token grant of RFC 6749, Section 6 carries neither a code nor a verifier.
     */
    public function testAsksForNoCodeVerifierOnTheRefreshTokenGrant()
    {
        $options = (new NoClientAuthentication())->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => ['grant_type' => 'refresh_token', 'refresh_token' => 'refresh-123']]);

        $this->assertSame('refresh-123', $options['body']['refresh_token']);
    }

    /**
     * A code redeemed under another name is a code redeemed: what the verifier binds is
     * checked by its presence and not only by the grant naming it.
     */
    public function testRefusesACodeRedeemedUnderAnotherGrantName()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must exchange the authorization code with PKCE');

        (new NoClientAuthentication())->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => ['grant_type' => 'urn:example:grant', 'code' => 'an-authorization-code']]);
    }

    /**
     * RFC 8628, Section 3.4: the device code is what a public client redeeming it holds, and
     * no browser redirect carried it, so there is nothing for a verifier to bind.
     */
    public function testAsksForNoCodeVerifierOnTheDeviceCodeGrant()
    {
        $options = (new NoClientAuthentication())->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => ['grant_type' => 'urn:ietf:params:oauth:grant-type:device_code', 'device_code' => 'device-123']]);

        $this->assertSame('device-123', $options['body']['device_code']);
    }

    /**
     * RFC 7009: a public client revokes the token it holds, which names no code and no grant.
     */
    public function testAsksForNoCodeVerifierOnARevocation()
    {
        $options = (new NoClientAuthentication())->authenticate('test-client-id', 'https://provider.example.com/revoke', ['body' => ['token' => 'refresh-123', 'token_type_hint' => 'refresh_token']]);

        $this->assertSame('refresh-123', $options['body']['token']);
    }

    public function testReportsItsMethod()
    {
        $this->assertSame('none', (new NoClientAuthentication())->getMethod());
    }
}
