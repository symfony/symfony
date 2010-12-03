<?php

namespace Symfony\Tests\Component\HttpKernel\Security\Logout;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Security\Logout\CookieClearingLogoutHandler;

class CookieClearingLogoutHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $cookieNames = array('foo', 'foo2', 'foo3');
        
        $handler = new CookieClearingLogoutHandler($cookieNames);
        
        $this->assertEquals($cookieNames, $handler->getCookieNames());
    }
    
    public function testLogout()
    {
        $request = new Request();
        $response = new Response();
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        
        $handler = new CookieClearingLogoutHandler(array('foo', 'foo2'));
        
        $this->assertFalse($response->headers->has('Set-Cookie'));
        
        $handler->logout($request, $response, $token);
        
        $headers = $response->headers->all();
        $cookies = $headers['set-cookie'];
        $this->assertEquals(2, count($cookies));
        
        $cookie = $cookies[0];
        $this->assertStringStartsWith('foo=;', $cookie);
        
        $cookie = $cookies[1];
        $this->assertStringStartsWith('foo2=;', $cookie);
    }
}