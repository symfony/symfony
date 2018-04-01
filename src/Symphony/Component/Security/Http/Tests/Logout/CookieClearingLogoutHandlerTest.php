<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests\Logout;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpFoundation\ResponseHeaderBag;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Security\Http\Logout\CookieClearingLogoutHandler;

class CookieClearingLogoutHandlerTest extends TestCase
{
    public function testLogout()
    {
        $request = new Request();
        $response = new Response();
        $token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        $handler = new CookieClearingLogoutHandler(array('foo' => array('path' => '/foo', 'domain' => 'foo.foo'), 'foo2' => array('path' => null, 'domain' => null)));

        $cookies = $response->headers->getCookies();
        $this->assertCount(0, $cookies);

        $handler->logout($request, $response, $token);

        $cookies = $response->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $this->assertCount(2, $cookies);

        $cookie = $cookies['foo.foo']['/foo']['foo'];
        $this->assertEquals('foo', $cookie->getName());
        $this->assertEquals('/foo', $cookie->getPath());
        $this->assertEquals('foo.foo', $cookie->getDomain());
        $this->assertTrue($cookie->isCleared());

        $cookie = $cookies['']['/']['foo2'];
        $this->assertStringStartsWith('foo2', $cookie->getName());
        $this->assertEquals('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertTrue($cookie->isCleared());
    }
}
