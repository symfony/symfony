<?php

namespace Symfony\Tests\Component\Security\Http\EntryPoint;

use Symfony\Component\Security\Http\EntryPoint\DigestAuthenticationEntryPoint;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\NonceExpiredException;

class DigestAuthenticationEntryPointTest extends \PHPUnit_Framework_TestCase
{
    public function testStart()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $authenticationException = new AuthenticationException('TheAuthenticationExceptionMessage');

        $entryPoint = new DigestAuthenticationEntryPoint('TheRealmName', 'TheKey');
        $response = $entryPoint->start($request, $authenticationException);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertAttributeEquals('TheAuthenticationExceptionMessage', 'statusText', $response);
        $this->assertRegExp('/^Digest realm="TheRealmName", qop="auth", nonce="[a-zA-Z0-9\/+]+={0,2}"$/', $response->headers->get('WWW-Authenticate'));
    }

    public function testStartWithNoException()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $entryPoint = new DigestAuthenticationEntryPoint('TheRealmName', 'TheKey');
        $response = $entryPoint->start($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertAttributeEquals('Unauthorized', 'statusText', $response);
        $this->assertRegExp('/^Digest realm="TheRealmName", qop="auth", nonce="[a-zA-Z0-9\/+]+={0,2}"$/', $response->headers->get('WWW-Authenticate'));
    }

    public function testStartWithNonceExpiredException()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $nonceExpiredException = new NonceExpiredException('TheNonceExpiredExceptionMessage');

        $entryPoint = new DigestAuthenticationEntryPoint('TheRealmName', 'TheKey');
        $response = $entryPoint->start($request, $nonceExpiredException);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertAttributeEquals('TheNonceExpiredExceptionMessage', 'statusText', $response);
        $this->assertRegExp('/^Digest realm="TheRealmName", qop="auth", nonce="[a-zA-Z0-9\/+]+={0,2}", stale="true"$/', $response->headers->get('WWW-Authenticate'));
    }
}
