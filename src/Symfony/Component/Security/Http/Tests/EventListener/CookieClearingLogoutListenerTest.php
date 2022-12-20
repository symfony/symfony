<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\EventListener\CookieClearingLogoutListener;

class CookieClearingLogoutListenerTest extends TestCase
{
    public function testLogout()
    {
        $response = new Response();
        $event = new LogoutEvent(new Request(), null);
        $event->setResponse($response);

        $listener = new CookieClearingLogoutListener(['foo' => ['path' => '/foo', 'domain' => 'foo.foo', 'secure' => true, 'samesite' => Cookie::SAMESITE_STRICT], 'foo2' => ['path' => null, 'domain' => null]]);

        $cookies = $response->headers->getCookies();
        self::assertCount(0, $cookies);

        $listener->onLogout($event);

        $cookies = $response->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        self::assertCount(2, $cookies);

        $cookie = $cookies['foo.foo']['/foo']['foo'];
        self::assertEquals('foo', $cookie->getName());
        self::assertEquals('/foo', $cookie->getPath());
        self::assertEquals('foo.foo', $cookie->getDomain());
        self::assertEquals(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isCleared());

        $cookie = $cookies['']['/']['foo2'];
        self::assertStringStartsWith('foo2', $cookie->getName());
        self::assertEquals('/', $cookie->getPath());
        self::assertNull($cookie->getDomain());
        self::assertNull($cookie->getSameSite());
        self::assertFalse($cookie->isSecure());
        self::assertTrue($cookie->isCleared());
    }
}
