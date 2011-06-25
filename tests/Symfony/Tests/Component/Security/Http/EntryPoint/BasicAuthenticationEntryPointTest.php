<?php

namespace Symfony\Tests\Component\Security\Http\EntryPoint;

use Symfony\Component\Security\Http\EntryPoint\BasicAuthenticationEntryPoint;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class BasicAuthenticationEntryPointTest extends \PHPUnit_Framework_TestCase
{
    public function testStart()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $authException = new AuthenticationException('The exception message');

        $entryPoint = new BasicAuthenticationEntryPoint('TheRealmName');
        $response = $entryPoint->start($request, $authException);

        $this->assertEquals('Basic realm="TheRealmName"', $response->headers->get('WWW-Authenticate'));
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertAttributeEquals('The exception message', 'statusText', $response);
    }

    public function testStartWithoutAuthException()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $entryPoint = new BasicAuthenticationEntryPoint('TheRealmName');

        $response = $entryPoint->start($request);

        $this->assertEquals('Basic realm="TheRealmName"', $response->headers->get('WWW-Authenticate'));
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertAttributeEquals('Unauthorized', 'statusText', $response);
    }
}
