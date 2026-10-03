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
    public function testLeavesTheRequestUntouched()
    {
        $clientAuthentication = new TlsClientAuth();

        $options = $clientAuthentication->authenticate('test-client-id', 'https://mtls.provider.example.com/token', [
            'body' => ['grant_type' => 'authorization_code', 'code' => 'auth-code'],
        ]);

        $this->assertSame(['body' => ['grant_type' => 'authorization_code', 'code' => 'auth-code']], $options);
    }

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
