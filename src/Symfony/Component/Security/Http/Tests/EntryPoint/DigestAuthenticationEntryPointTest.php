<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EntryPoint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\NonceExpiredException;
use Symfony\Component\Security\Http\EntryPoint\DigestAuthenticationEntryPoint;

class DigestAuthenticationEntryPointTest extends TestCase
{
    public function testStart()
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();

        $authenticationException = new AuthenticationException('TheAuthenticationExceptionMessage');

        $entryPoint = new DigestAuthenticationEntryPoint('TheRealmName', 'TheSecret');
        $response = $entryPoint->start($request, $authenticationException);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertRegExp('/^Digest realm="TheRealmName", qop="auth", nonce="[a-zA-Z0-9\/+]+={0,2}"$/', $response->headers->get('WWW-Authenticate'));
    }

    public function testStartWithNoException()
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();

        $entryPoint = new DigestAuthenticationEntryPoint('TheRealmName', 'TheSecret');
        $response = $entryPoint->start($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertRegExp('/^Digest realm="TheRealmName", qop="auth", nonce="[a-zA-Z0-9\/+]+={0,2}"$/', $response->headers->get('WWW-Authenticate'));
    }

    public function testStartWithNonceExpiredException()
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();

        $nonceExpiredException = new NonceExpiredException('TheNonceExpiredExceptionMessage');

        $entryPoint = new DigestAuthenticationEntryPoint('TheRealmName', 'TheSecret');
        $response = $entryPoint->start($request, $nonceExpiredException);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertRegExp('/^Digest realm="TheRealmName", qop="auth", nonce="[a-zA-Z0-9\/+]+={0,2}", stale="true"$/', $response->headers->get('WWW-Authenticate'));
    }
}
