<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Http\Logout;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Logout\CookieClearingLogoutHandler;

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
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $handler = new CookieClearingLogoutHandler(array('foo', 'foo2'));

        $this->assertFalse($response->headers->hasCookie('foo'));

        $handler->logout($request, $response, $token);

        $cookies = $response->headers->getCookies();
        $this->assertEquals(2, count($cookies));

        $cookie = $cookies['foo'];
        $this->assertEquals('foo', $cookie->getName());
        $this->assertTrue($cookie->isCleared());

        $cookie = $cookies['foo2'];
        $this->assertStringStartsWith('foo2', $cookie->getName());
        $this->assertTrue($cookie->isCleared());
    }
}
